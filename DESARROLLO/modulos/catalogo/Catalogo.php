<?php
declare(strict_types=1);

namespace RPV\Catalogo;

final class Catalogo
{
    public const IDS = ['caldera-tipo-a', 'caldera-tipo-b', 'caldera-tipo-c', 'tinaja-4-personas', 'tinaja-7-personas', 'estufa-pellet'];
    public const TEXTOS = ['rendimiento', 'incluye', 'instalacion', 'despacho', 'garantia'];
    private string $root;
    private \PDO $pdo;
    private array $publishedBefore = [];
    public function __construct(?string $root = null, ?\PDO $pdo = null) {
        $this->root = $root ?? dirname(__DIR__, 2);
        $this->pdo = $pdo ?? $this->conectar();
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    }
    private function conectar(): \PDO {
        $pdo = null;
        require $this->root . '/BaseDatos/conexion_rpv.php';
        if (!$pdo instanceof \PDO) throw new \RuntimeException('No se pudo conectar a la base local.');
        $environment = $pdo->query('SELECT DATABASE() AS db, @@port AS port')->fetch(\PDO::FETCH_ASSOC);
        if (strcasecmp((string)$environment['db'], 'rpvconst_DataMain_dev') !== 0 || (int)$environment['port'] !== 4444) throw new \RuntimeException('El catálogo requiere MariaDB local de desarrollo, puerto 4444.');
        $pdo->exec("SET time_zone = '+00:00'");
        return $pdo;
    }
    // Copia exportada para inspección y respaldo; productos y precios son las fuentes comerciales.
    private function source(): string { return $this->root . '/catalogo/productos.json'; }
    public function leer(bool $lock = false): array {
        $sql = 'SELECT c.id_producto, producto.nombre, producto.categoria, precio.precio_clp, precio.estado AS modo_precio, precio.link_pago, c.rendimiento, c.incluye, c.instalacion, c.despacho, c.garantia, c.aprobado, c.revision, c.actualizado_el FROM catalogo_productos c INNER JOIN productos producto ON producto.id_producto = c.id_producto INNER JOIN precios precio ON precio.id_producto = producto.id_producto ORDER BY c.id_producto';
        if ($lock && $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'mysql') $sql .= ' FOR UPDATE';
        $rows = $this->pdo->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
        if (count($rows) !== count(self::IDS)) throw new \RuntimeException('El catálogo está incompleto. Ejecuta aplicar-migracion-catalogo.php.');
        $map = array_column($rows, null, 'id_producto');
        $products = []; $revisions = []; $updated = null;
        foreach (self::IDS as $id) {
            if (!isset($map[$id])) throw new \RuntimeException('Falta un producto del catálogo.');
            $row = $map[$id];
            $date = empty($row['actualizado_el']) ? null : (new \DateTimeImmutable($row['actualizado_el'], new \DateTimeZone('UTC')))->format(DATE_ATOM);
            $p = ['id'=>$id,'nombre'=>$row['nombre'],'categoria'=>$row['categoria'],'precio_clp'=>$row['precio_clp'] === null ? null : (int)$row['precio_clp'],'modo_precio'=>$row['modo_precio'], 'link_pago'=>$row['link_pago'] ?? ''];
            foreach (self::TEXTOS as $key) $p[$key] = $row[$key];
            $p['aprobado'] = (bool)$row['aprobado']; $p['actualizado_el'] = $date;
            $this->validar($p); $products[] = $p; $revisions[] = $row['revision'];
            if ($date !== null && ($updated === null || $date > $updated)) $updated = $date;
        }
        $revision = hash('sha256', $this->json($products) . implode('|', $revisions));
        return ['schema_version'=>1,'revision'=>$revision,'actualizado_el'=>$updated,'productos'=>$products];
    }
    private function texto(mixed $value, int $max = 1200): string {
        if (!is_string($value) || preg_match('//u', $value) !== 1) throw new \InvalidArgumentException('Ingresa texto válido.');
        $value = trim((string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value));
        if (strlen($value) > $max * 4 || (function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value)) > $max) throw new \InvalidArgumentException("El texto supera $max caracteres.");
        return $value;
    }
    private function validar(array $p): void {
        if (!in_array($p['id'] ?? '', self::IDS, true) || !in_array($p['categoria'] ?? '', ['calderas','tinajas','estufas'], true)) throw new \InvalidArgumentException('Producto inválido.');
        if (!in_array($p['modo_precio'] ?? '', ['referencial','vigente','consultar'], true)) throw new \InvalidArgumentException('Selecciona un estado de precio válido.');
        $price = $p['precio_clp'] ?? null;
        if (!is_int($price) || $price < 1 || $price > 2000000) throw new \InvalidArgumentException('El precio debe ser un entero entre 1 y 2.000.000 pesos.');
        $link = $p['link_pago'] ?? '';
        if (!is_string($link) || strlen($link) > 2048 || ($link !== '' && (!filter_var($link, FILTER_VALIDATE_URL) || parse_url($link, PHP_URL_SCHEME) !== 'https'))) throw new \InvalidArgumentException('Ingresa un link de pago HTTPS válido.');
        foreach (array_merge(['nombre'], self::TEXTOS) as $key) {
            if (is_string($p[$key] ?? null) && preg_match('/\$\s*\d|\b\d[\d.,]*\s*(?:CLP|pesos)\b/iu', $p[$key])) throw new \InvalidArgumentException('Usa el campo Precio para los montos; no repitas valores en los textos comerciales.');
            if ($this->texto($p[$key] ?? null) === '') throw new \InvalidArgumentException('Completa todos los textos comerciales. Puedes indicar que un dato está sujeto a confirmación.');
        }
    }
    public function guardar(string $id, array $input, string $revision, ?callable $audit = null): array {
        return $this->conBloqueo(function () use ($id, $input, $revision, $audit) {
            $this->publishedBefore = [];
            $this->pdo->beginTransaction();
            try {
                $data = $this->leer(true);
                if (!hash_equals($data['revision'], $revision)) throw new \RuntimeException('El catálogo cambió en otra ventana. Recarga antes de guardar para no sobrescribir esos cambios.');
                $index = array_search($id, self::IDS, true);
                if ($index === false) throw new \InvalidArgumentException('El producto no existe.');
                if (($input['aprobar'] ?? '') !== '1') throw new \InvalidArgumentException('Confirma los datos comerciales antes de guardar.');
                $p = $data['productos'][$index];
                $p['modo_precio'] = $this->texto($input['modo_precio'] ?? '', 20);
                $raw = $input['precio_clp'] ?? '';
                if (!is_string($raw)) throw new \InvalidArgumentException('Precio inválido.');
                if (!preg_match('/^(?:[1-9][0-9]*|[1-9][0-9]{0,2}(?:\.[0-9]{3})+)$/D', $raw)) throw new \InvalidArgumentException('Escribe un precio válido en pesos.');
                $p['precio_clp'] = (int) str_replace('.', '', $raw);
                $p['link_pago'] = $this->texto($input['link_pago'] ?? '', 2048);
                foreach (self::TEXTOS as $key) $p[$key] = $this->texto($input[$key] ?? '');
                $p['aprobado'] = true;
                $this->validar($p);
                $columns = self::TEXTOS;
                $values = [];
                foreach ($columns as $key) $values[] = $p[$key];
                $values[] = bin2hex(random_bytes(16)); $values[] = gmdate('Y-m-d H:i:s'); $values[] = $id;
                $sql = 'UPDATE catalogo_productos SET ' . implode(', ', array_map(fn($key)=>$key . '=?', $columns)) . ', aprobado=1, revision=?, actualizado_el=? WHERE id_producto=?';
                $this->pdo->prepare($sql)->execute($values);
                $this->pdo->prepare('UPDATE precios SET precio_clp=?, estado=?, link_pago=? WHERE id_producto=?')->execute([$p['precio_clp'], $p['modo_precio'], $p['link_pago'] ?: null, $id]);
                $saved = $this->leer(true);
                $targets = [$this->source()=>$this->json($saved)] + $this->artefactos($saved);
                $history = $this->root . '/catalogo/historial';
                if (!is_dir($history) && !mkdir($history, 0770, true)) throw new \RuntimeException('No se pudo crear el historial.');
                $old = $this->json($data);
                if (file_put_contents($history . '/' . date('Ymd-His') . '-' . bin2hex(random_bytes(6)) . '.json', $old, LOCK_EX) !== strlen($old)) throw new \RuntimeException('No se pudo respaldar la revisión anterior.');
                $this->publicar($targets);
                if ($audit !== null) $audit($this->pdo, $data['productos'][$index], $saved['productos'][$index]);
                $this->pdo->commit();
                $this->publishedBefore = [];
                return $saved;
            } catch (\Throwable $e) {
                if ($this->pdo->inTransaction()) $this->pdo->rollBack();
                if ($this->publishedBefore) { $this->restaurar($this->publishedBefore); $this->publishedBefore = []; }
                throw $e;
            }
        });
    }
    private function conBloqueo(callable $fn): mixed {
        $handle = fopen($this->root . '/catalogo/.catalogo.lock', 'c');
        if (!$handle || !flock($handle, LOCK_EX)) throw new \RuntimeException('No se pudo bloquear el catálogo.');
        try { return $fn(); } finally { flock($handle, LOCK_UN); fclose($handle); }
    }
    private function json(array $data): string { return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n"; }
    public function precioTexto(array $p): string {
        if ($p['modo_precio'] === 'consultar') return 'Precio a consultar';
        return '$' . number_format($p['precio_clp'], 0, ',', '.') . ' CLP' . ($p['modo_precio'] === 'referencial' ? ' · referencial' : '');
    }
    private function publico(array $p): array {
        $p['precio_texto'] = $this->precioTexto($p);
        $p['vigencia_texto'] = 'Confirma precio y disponibilidad al cotizar.';
        $p['respuesta'] = $p['nombre'] . ': ' . $p['precio_texto'] . '. ' . $p['rendimiento'] . ' Incluye: ' . $p['incluye'] . ' ' . $p['vigencia_texto'];
        return $p;
    }
    public function artefactos(array $data): array {
        $products = array_map(fn($p) => $this->publico($p), $data['productos']);
        $map = array_column($products, null, 'id');
        $base = (string) file_get_contents($this->root . '/modulos/catalogo/conocimiento-base.md');
        if (preg_match('/\$\s*\d|\bEquipo\s+[123]\b/iu', $base)) throw new \RuntimeException('La base editorial contiene precios o modelos retirados. Usa el catálogo comercial.');
        $md = "# RPV Construcciones — Base de conocimiento del asistente\n\n> Generado desde el catálogo comercial. Revisión: " . $data['revision'] . ". Administrar desde el panel de precios; no editar este archivo generado.\n\n";
        foreach (['calderas'=>'calderas','tinajas'=>'tinajas','estufas'=>'estufas a pellets'] as $category=>$name) {
            $md .= "## ¿Cuáles son los precios de las $name?\n";
            foreach ($products as $p) if ($p['categoria'] === $category) $md .= $p['respuesta'] . "\n";
            $md .= "\n";
        }
        foreach ($products as $p) {
            $md .= '## ¿Cuánto cuesta ' . $p['nombre'] . "?\n" . $p['respuesta'] . "\n\n";
            foreach (self::TEXTOS as $key) $md .= '## ' . ucfirst($key) . ' — ' . $p['nombre'] . "\n" . $p[$key] . "\n\n";
        }
        $md .= "## ¿A qué tipo corresponden los antiguos equipos numerados?\nLas referencias antiguas no tienen una equivalencia aprobada. Indica caldera tipo A, B o C, o consulta con el equipo por WhatsApp.\n\n" . trim($base) . "\n";
        $targets = [];
        foreach (['index.html','calderas/index.html','tinajas/index.html','estufas-a-pellets/index.html'] as $file) {
            $filename = $this->root . '/Web/' . $file;
            $html = (string) file_get_contents($filename);
            $html = preg_replace_callback('~(<(span|p|h3|strong)\b[^>]*data-catalog-product="([^"]+)"[^>]*data-catalog-field="([^"]+)"[^>]*>)[^<]*(</\2>)~u', function($m) use ($map) {
                if (!isset($map[$m[3]])) throw new \RuntimeException('Identificador desconocido en el sitio: ' . $m[3]);
                if ($m[4] === 'precio') {
                    $value = $map[$m[3]]['precio_texto'];
                } elseif ($m[4] === 'precio_registrado') {
                    $amount = $map[$m[3]]['precio_clp'] ?? null;
                    $value = is_int($amount) ? '$' . number_format($amount, 0, ',', '.') . ' CLP' : 'Precio a confirmar por WhatsApp';
                } else {
                    $value = $map[$m[3]][$m[4]] ?? null;
                }
                if (!is_string($value)) throw new \RuntimeException('Campo desconocido en el sitio: ' . $m[4]);
                return $m[1] . htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . $m[5];
            }, $html);
            if ($html === null) throw new \RuntimeException('No se pudo sincronizar ' . $file);
            $targets[$filename] = $html;
        }
        $targets[$this->root . '/Web/assets/data/conocimiento-rpv.md'] = $md;
        $targets[$this->root . '/Web/assets/data/catalogo-rpv.json'] = $this->json(['schema_version'=>1,'revision'=>$data['revision'],'actualizado_el'=>$data['actualizado_el'],'productos'=>$products,'conocimiento'=>$md]);
        return $targets;
    }
    public function sincronizar(bool $check = false): void {
        $this->conBloqueo(function () use ($check) {
            $data = $this->leer();
            $targets = [$this->source()=>$this->json($data)] + $this->artefactos($data);
            if ($check) {
                foreach ($targets as $file=>$content) if (!is_file($file) || file_get_contents($file) !== $content) throw new \RuntimeException('Catálogo sin sincronizar: ' . basename($file));
            } else { $this->publicar($targets); $this->publishedBefore = []; }
        });
    }
    private function reemplazar(string $file, string $content): void {
        $temp = tempnam(dirname($file), '.rpv-catalogo-');
        if ($temp === false) throw new \RuntimeException('No se pudo preparar la sincronización.');
        try {
            if (file_put_contents($temp, $content, LOCK_EX) !== strlen($content) || !@rename($temp, $file)) throw new \RuntimeException('No se pudo actualizar ' . basename($file));
        } finally { if (is_file($temp)) unlink($temp); }
    }
    private function restaurar(array $before): void {
        foreach (array_reverse($before, true) as $file=>$old) {
            if ($old === null) { if (is_file($file)) unlink($file); }
            else $this->reemplazar($file, $old);
        }
    }
    private function publicar(array $targets): void {
        $before = [];
        try {
            foreach ($targets as $file=>$content) {
                if (is_file($file) && file_get_contents($file) === $content) continue;
                $before[$file] = is_file($file) ? file_get_contents($file) : null;
                $this->reemplazar($file, $content);
            }
        } catch (\Throwable $e) {
            $this->restaurar($before);
            throw new \RuntimeException('No se guardó el cambio. Revisa permisos de escritura del catálogo y sus archivos generados.', 0, $e);
        }
        $this->publishedBefore = $before;
    }
}
