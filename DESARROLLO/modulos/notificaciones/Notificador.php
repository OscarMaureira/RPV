<?php
declare(strict_types=1);

namespace RPV\Modulos\Notificaciones;

use RuntimeException;

/**
 * Cliente SMTP central para los scripts PHP del sitio.
 * Usa TLS implícito (SMTP SSL, puerto 465) y parámetros de un archivo global privado.
 */
final class Notificador
{
    private const TIMEOUT_SECONDS = 12;
    private const MAX_RECIPIENTS = 10;

    /**
     * @param string|list<string> $destinatarios Direcciones de correo validadas.
     * @param string $asunto Asunto del mensaje.
     * @param string $textoPlano Versión de texto plano del mensaje.
     * @param string|null $html Versión HTML opcional del mensaje.
     * @param list<string> $copiasOcultas Destinatarios BCC que no aparecen en las cabeceras.
     */
    public static function enviar(
        string|array $destinatarios,
        string $asunto,
        string $textoPlano,
        ?string $html = null,
        string $tipo = 'general',
        array $copiasOcultas = []
    ): void {
        $tipo = preg_match('/^[a-z0-9_-]{1,40}$/i', $tipo) ? strtolower($tipo) : 'general';
        try {
            self::enviarViaSmtp($destinatarios, $asunto, $textoPlano, $html, $copiasOcultas);
            self::registrarResultado($tipo, $destinatarios, 'ACEPTADO_SMTP');
        } catch (\Throwable $error) {
            self::registrarResultado($tipo, $destinatarios, 'FALLIDO', $error->getMessage());
            throw $error;
        }
    }

    private static function enviarViaSmtp(
        string|array $destinatarios,
        string $asunto,
        string $textoPlano,
        ?string $html,
        array $copiasOcultas
    ): void {
        $config = self::configuracion();
        $destinatarios = self::validarDestinatarios($destinatarios);
        $copiasOcultas = $copiasOcultas === [] ? [] : self::validarDestinatarios($copiasOcultas);
        $copiasOcultas = array_values(array_diff($copiasOcultas, $destinatarios));
        if (count($destinatarios) + count($copiasOcultas) > self::MAX_RECIPIENTS) {
            throw new RuntimeException('El mensaje supera el máximo de destinatarios.');
        }
        $asunto = trim($asunto);
        $textoPlano = trim($textoPlano);

        if ($asunto === '' || strlen($asunto) > 1200 || preg_match('/[\r\n\x00]/', $asunto)) {
            throw new RuntimeException('El asunto del correo no es válido.');
        }
        if ($textoPlano === '' || strlen($textoPlano) > 1_000_000) {
            throw new RuntimeException('El mensaje de texto no es válido.');
        }
        if ($html !== null && strlen($html) > 1_000_000) {
            throw new RuntimeException('El mensaje HTML supera el tamaño permitido.');
        }

        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'peer_name' => $config['host'],
                'SNI_enabled' => true,
                'crypto_method' => STREAM_CRYPTO_METHOD_TLS_CLIENT,
            ],
        ]);
        $socket = @stream_socket_client(
            'ssl://' . $config['host'] . ':' . $config['port'],
            $errorCode,
            $errorMessage,
            self::TIMEOUT_SECONDS,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (!is_resource($socket)) {
            throw new RuntimeException('No fue posible conectar con el servidor de correo.');
        }

        stream_set_timeout($socket, self::TIMEOUT_SECONDS);

        try {
            self::leerRespuesta($socket, [220], 'saludo');
            self::comando($socket, 'EHLO ' . $config['ehlo'], [250], 'identificación');
            self::comando($socket, 'AUTH LOGIN', [334], 'autenticación');
            self::comando($socket, base64_encode($config['username']), [334], 'usuario');
            self::comando($socket, base64_encode($config['password']), [235], 'contraseña');
            self::comando($socket, 'MAIL FROM:<' . $config['from'] . '>', [250], 'remitente');

            foreach (array_merge($destinatarios, $copiasOcultas) as $destinatario) {
                self::comando($socket, 'RCPT TO:<' . $destinatario . '>', [250, 251, 252], 'destinatario');
            }

            self::comando($socket, 'DATA', [354], 'contenido');
            self::escribir($socket, self::construirMensaje($config, $destinatarios, $asunto, $textoPlano, $html));
            self::escribir($socket, "\r\n.\r\n");
            self::leerRespuesta($socket, [250], 'entrega');

            // La entrega ya fue aceptada; el cierre no debe convertirla en un falso error.
            @fwrite($socket, "QUIT\r\n");
        } finally {
            fclose($socket);
        }
    }

    private static function registrarResultado(
        string $tipo,
        string|array $destinatarios,
        string $resultado,
        string $detalle = ''
    ): void {
        $rutaLogs = dirname(__DIR__, 2) . '/logs';
        $rutaArchivo = $rutaLogs . '/notificaciones.log';

        try {
            if (!is_dir($rutaLogs) && !@mkdir($rutaLogs, 0700, true) && !is_dir($rutaLogs)) {
                throw new RuntimeException('No se pudo crear la carpeta de logs.');
            }

            $lista = is_string($destinatarios) ? [$destinatarios] : $destinatarios;
            $correos = [];
            foreach ($lista as $destinatario) {
                if (!is_string($destinatario)) {
                    continue;
                }
                $correo = strtolower(trim($destinatario));
                if (!preg_match('/[\r\n\x00]/', $correo) && filter_var($correo, FILTER_VALIDATE_EMAIL) !== false) {
                    $correos[] = $correo;
                }
            }
            $correos = array_values(array_unique($correos));
            $detalle = preg_replace('/[\r\n\t\x00-\x1F\x7F]+/', ' ', $detalle) ?? '';
            $detalle = substr(trim($detalle), 0, 240);

            $linea = gmdate('Y-m-d\TH:i:s\Z')
                . ' | resultado=' . $resultado
                . ' | tipo=' . $tipo
                . ' | destinatarios=' . count($correos)
                . ' | correo_destinatario=' . ($correos ? implode(',', $correos) : 'no_disponible')
                . ($detalle !== '' ? ' | detalle=' . $detalle : '')
                . PHP_EOL;

            $archivo = @fopen($rutaArchivo, 'ab');
            if ($archivo === false) {
                throw new RuntimeException('No se pudo abrir el archivo de log.');
            }

            try {
                if (!flock($archivo, LOCK_EX) || fwrite($archivo, $linea) === false) {
                    throw new RuntimeException('No se pudo agregar el resultado al log.');
                }
                fflush($archivo);
                flock($archivo, LOCK_UN);
            } finally {
                fclose($archivo);
            }
        } catch (\Throwable $logError) {
            error_log('RPV notificaciones: no se pudo escribir notificaciones.log. ' . $logError->getMessage());
        }
    }

    /** @return array{host:string,port:int,username:string,password:string,from:string,from_name:string,reply_to:string,ehlo:string} */
    private static function configuracion(): array
    {
        $directorioUsuario = trim((string) (
            PHP_OS_FAMILY === 'Windows'
                ? (getenv('USERPROFILE') ?: getenv('HOME'))
                : (getenv('HOME') ?: dirname(__DIR__, 2))
        ));
        if ($directorioUsuario === '') {
            throw new RuntimeException('PHP no informa HOME ni USERPROFILE para localizar la configuración SMTP global.');
        }

        $rutaConfiguracion = rtrim($directorioUsuario, '/\\')
            . DIRECTORY_SEPARATOR . '.config'
            . DIRECTORY_SEPARATOR . 'rpv'
            . DIRECTORY_SEPARATOR . 'notificaciones.php';
        if (!is_file($rutaConfiguracion) || !is_readable($rutaConfiguracion)) {
            throw new RuntimeException('Falta el archivo de configuración SMTP global: ' . $rutaConfiguracion);
        }

        $config = require $rutaConfiguracion;
        if (!is_array($config)) {
            throw new RuntimeException('El archivo de configuración SMTP global debe devolver un arreglo PHP.');
        }

        $host = trim((string) ($config['host'] ?? 'mail.rpvconstrucciones.com'));
        $username = trim((string) ($config['username'] ?? 'contacto@rpvconstrucciones.com'));
        $password = trim((string) ($config['password'] ?? ''));
        $from = trim((string) ($config['from'] ?? $username));
        $replyTo = trim((string) ($config['reply_to'] ?? ''));
        $port = filter_var($config['port'] ?? 465, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 65535],
        ]);
        $ehlo = trim((string) ($config['ehlo'] ?? 'rpvconstrucciones.com'));
        $fromName = trim((string) ($config['from_name'] ?? 'RPV Construcciones'));

        if ($password === '') {
            throw new RuntimeException('Falta definir password en el archivo de configuración SMTP global.');
        }
        if ($port === false || filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false) {
            throw new RuntimeException('La configuración del servidor SMTP no es válida.');
        }
        self::validarCorreo($username, 'usuario SMTP');
        self::validarCorreo($from, 'remitente');
        if (strcasecmp($from, $username) !== 0) {
            throw new RuntimeException('El remitente debe coincidir con el usuario SMTP autenticado.');
        }
        if ($replyTo !== '') {
            self::validarCorreo($replyTo, 'respuesta');
        }
        if (preg_match('/[^a-zA-Z0-9.-]/', $ehlo)) {
            throw new RuntimeException('El nombre EHLO no es válido.');
        }

        return [
            'host' => $host,
            'port' => $port,
            'username' => $username,
            'password' => $password,
            'from' => $from,
            'from_name' => $fromName,
            'reply_to' => $replyTo,
            'ehlo' => $ehlo,
        ];
    }

    /** @param string|list<string> $destinatarios @return list<string> */
    private static function validarDestinatarios(string|array $destinatarios): array
    {
        $lista = is_string($destinatarios) ? [$destinatarios] : array_values($destinatarios);
        if (count($lista) < 1 || count($lista) > self::MAX_RECIPIENTS) {
            throw new RuntimeException('Indica entre 1 y 10 destinatarios.');
        }

        foreach ($lista as $destinatario) {
            if (!is_string($destinatario)) {
                throw new RuntimeException('La lista de destinatarios no es válida.');
            }
            self::validarCorreo(trim($destinatario), 'destinatario');
        }

        return array_values(array_unique(array_map('strtolower', array_map('trim', $lista))));
    }

    private static function validarCorreo(string $correo, string $campo): void
    {
        if (preg_match('/[\r\n\x00]/', $correo) || filter_var($correo, FILTER_VALIDATE_EMAIL) === false) {
            throw new RuntimeException('La dirección de ' . $campo . ' no es válida.');
        }
    }

    /** @param resource $socket @param list<int> $codigos */
    private static function comando($socket, string $comando, array $codigos, string $etapa): void
    {
        self::escribir($socket, $comando . "\r\n");
        self::leerRespuesta($socket, $codigos, $etapa);
    }

    /** @param resource $socket @param list<int> $codigos */
    private static function leerRespuesta($socket, array $codigos, string $etapa): int
    {
        $codigo = 0;
        do {
            $linea = fgets($socket, 515);
            if ($linea === false || stream_get_meta_data($socket)['timed_out']) {
                throw new RuntimeException('El servidor de correo agotó el tiempo durante ' . $etapa . '.');
            }
            if (!preg_match('/^(\d{3})([ -])/', $linea, $partes)) {
                throw new RuntimeException('El servidor de correo respondió en un formato no válido.');
            }
            $codigo = (int) $partes[1];
        } while ($partes[2] === '-');

        if (!in_array($codigo, $codigos, true)) {
            throw new RuntimeException('El servidor de correo rechazó ' . $etapa . ' (código ' . $codigo . ').');
        }

        return $codigo;
    }

    /** @param resource $socket */
    private static function escribir($socket, string $contenido): void
    {
        $total = strlen($contenido);
        $escritos = 0;
        while ($escritos < $total) {
            $n = fwrite($socket, substr($contenido, $escritos));
            if ($n === false || $n === 0) {
                throw new RuntimeException('No fue posible completar la comunicación con el servidor de correo.');
            }
            $escritos += $n;
        }
    }

    /** @param array{host:string,port:int,username:string,password:string,from:string,from_name:string,reply_to:string,ehlo:string} $config
     *  @param list<string> $destinatarios
     */
    private static function construirMensaje(
        array $config,
        array $destinatarios,
        string $asunto,
        string $textoPlano,
        ?string $html
    ): string {
        $cabeceras = [
            'Date: ' . gmdate('D, d M Y H:i:s O'),
            'From: ' . self::codificarCabecera($config['from_name']) . ' <' . $config['from'] . '>',
            'To: ' . (count($destinatarios) === 1 ? $destinatarios[0] : 'undisclosed-recipients:;'),
            'Subject: ' . self::codificarCabecera($asunto),
            'Message-ID: <' . bin2hex(random_bytes(16)) . '@' . $config['ehlo'] . '>',
            'MIME-Version: 1.0',
            'X-Mailer: RPV Notificaciones',
        ];
        if ($config['reply_to'] !== '') {
            $cabeceras[] = 'Reply-To: ' . $config['reply_to'];
        }

        if ($html === null) {
            $cabeceras[] = 'Content-Type: text/plain; charset=UTF-8';
            $cabeceras[] = 'Content-Transfer-Encoding: base64';
            $cuerpo = chunk_split(base64_encode($textoPlano), 76, "\r\n");
        } else {
            $limpio = bin2hex(random_bytes(18));
            $cabeceras[] = 'Content-Type: multipart/alternative; boundary="' . $limpio . '"';
            $cuerpo = '--' . $limpio . "\r\n"
                . "Content-Type: text/plain; charset=UTF-8\r\n"
                . "Content-Transfer-Encoding: base64\r\n\r\n"
                . chunk_split(base64_encode($textoPlano), 76, "\r\n")
                . '--' . $limpio . "\r\n"
                . "Content-Type: text/html; charset=UTF-8\r\n"
                . "Content-Transfer-Encoding: base64\r\n\r\n"
                . chunk_split(base64_encode($html), 76, "\r\n")
                . '--' . $limpio . "--\r\n";
        }

        return implode("\r\n", $cabeceras) . "\r\n\r\n" . $cuerpo;
    }

    private static function codificarCabecera(string $valor): string
    {
        return '=?UTF-8?B?' . base64_encode($valor) . '?=';
    }
}
