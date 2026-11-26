<?php
declare(strict_types=1);
require __DIR__.'/acceso.php';
require __DIR__.'/componentes/layout.php';
Permissions::require('usuarios.ver');

$error='';
$emailAvailable=(bool)$pdo->query("SHOW COLUMNS FROM admin_usuarios LIKE 'correo'")->fetch(PDO::FETCH_ASSOC);

if($_SERVER['REQUEST_METHOD']==='POST'){
    Csrf::validate($_POST['csrf']??null);
    Permissions::require('usuarios.administrar');
    $action=(string)($_POST['action']??'');
    $id=filter_var($_POST['id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
    $username=trim((string)($_POST['usuario']??''));
    $email=strtolower(trim((string)($_POST['correo']??'')));
    $password=(string)($_POST['password']??'');
    try{
        if(!in_array($action,['create','update','delete','change_password','revoke'],true))throw new InvalidArgumentException('Accion no valida.');
        if(!$emailAvailable&&in_array($action,['create','update'],true))throw new RuntimeException('Aplica la migracion del panel antes de administrar correos.');
        $pdo->beginTransaction();
        if($action==='create'){
            if(!preg_match('/^[A-Za-z0-9._-]{3,80}$/D',$username))throw new InvalidArgumentException('El usuario debe tener entre 3 y 80 caracteres: letras, numeros, punto, guion o guion bajo.');
            if(strcasecmp($username,'admin')===0)throw new InvalidArgumentException('El nombre admin esta reservado.');
            if(!filter_var($email,FILTER_VALIDATE_EMAIL)||textLength($email)>254)throw new InvalidArgumentException('Ingresa un correo electronico valido.');
            if(textLength($password)<12||textLength($password)>128)throw new InvalidArgumentException('La contrasena inicial debe tener entre 12 y 128 caracteres.');
            $stmt=$pdo->prepare('INSERT INTO admin_usuarios(usuario,correo,password_hash,activo) VALUES(?,?,?,1)');
            $stmt->execute([$username,$email,password_hash($password,Auth::algorithm())]);
            $id=(int)$pdo->lastInsertId();
            adminAudit($pdo,'cuenta_creada','cuenta',(string)$id,['usuario'=>$username,'correo_configurado'=>true,'activo'=>1]);
            $message='Usuario creado correctamente.';
        }else{
            if(!$id)throw new InvalidArgumentException('Cuenta no valida.');
            $columns=$emailAvailable?'id,usuario,correo,password_hash,activo':'id,usuario,NULL AS correo,password_hash,activo';
            $stmt=$pdo->prepare('SELECT '.$columns.' FROM admin_usuarios WHERE id=? FOR UPDATE');$stmt->execute([$id]);$before=$stmt->fetch(PDO::FETCH_ASSOC);
            if(!$before)throw new InvalidArgumentException('La cuenta ya no existe.');
            $protected=strcasecmp((string)$before['usuario'],'admin')===0;

            if($action==='update'){
                if($protected)throw new InvalidArgumentException('La cuenta admin no permite modificar su correo ni sus datos.');
                if(!preg_match('/^[A-Za-z0-9._-]{3,80}$/D',$username)||strcasecmp($username,'admin')===0)throw new InvalidArgumentException('Usuario no valido o reservado.');
                if(!filter_var($email,FILTER_VALIDATE_EMAIL)||textLength($email)>254)throw new InvalidArgumentException('Ingresa un correo electronico valido.');
                $active=($_POST['activo']??'')==='1'?1:0;
                if($id===Auth::id()&&!$active)throw new InvalidArgumentException('No puedes desactivar la cuenta con la sesion abierta.');
                if((int)$before['activo']===1&&!$active&&(int)$pdo->query('SELECT COUNT(*) FROM admin_usuarios WHERE activo=1')->fetchColumn()<=1)throw new InvalidArgumentException('Debe quedar al menos una cuenta activa.');
                $pdo->prepare('UPDATE admin_usuarios SET usuario=?,correo=?,activo=? WHERE id=?')->execute([$username,$email,$active,$id]);
                if($active!==(int)$before['activo'])Auth::revokeUserSessions($pdo,$id,$id===Auth::id());
                adminAudit($pdo,'cuenta_actualizada','cuenta',(string)$id,['antes'=>['usuario'=>$before['usuario'],'correo_configurado'=>$before['correo']!==null,'activo'=>(int)$before['activo']],'despues'=>['usuario'=>$username,'correo_configurado'=>true,'activo'=>$active]]);
                $message='Usuario actualizado correctamente.';
            }elseif($action==='change_password'){
                if($id!==Auth::id())throw new InvalidArgumentException('Solo puedes cambiar la contrasena de tu propia cuenta.');
                if((int)$before['activo']!==1)throw new InvalidArgumentException('La cuenta debe estar activa.');
                if(!password_verify((string)($_POST['current_password']??''),(string)$before['password_hash']))throw new InvalidArgumentException('La contrasena actual no es correcta.');
                if(textLength($password)<12||textLength($password)>128)throw new InvalidArgumentException('La nueva contrasena debe tener entre 12 y 128 caracteres.');
                $newHash=password_hash($password,Auth::algorithm());
                try{$pdo->prepare('UPDATE admin_usuarios SET password_hash=?,credenciales_version=credenciales_version+1 WHERE id=?')->execute([$newHash,$id]);}catch(PDOException $e){$pdo->prepare('UPDATE admin_usuarios SET password_hash=? WHERE id=?')->execute([$newHash,$id]);}
                adminAudit($pdo,'contrasena_cambiada','cuenta',(string)$id);
                Auth::revokeUserSessions($pdo,$id,true);$_SESSION['admin_password_stamp']=hash('sha256',$newHash);
                $message='Tu contrasena fue actualizada.';
            }elseif($action==='revoke'){
                Auth::revokeUserSessions($pdo,$id,$id===Auth::id());
                adminAudit($pdo,'sesiones_revocadas','cuenta',(string)$id,['usuario'=>$before['usuario']]);
                $message='Sesiones revocadas.';
            }else{
                if($protected)throw new InvalidArgumentException('La cuenta admin es permanente y no puede eliminarse.');
                if($id===Auth::id())throw new InvalidArgumentException('No puedes eliminar la cuenta con la sesion abierta.');
                if((int)$before['activo']===1&&(int)$pdo->query('SELECT COUNT(*) FROM admin_usuarios WHERE activo=1')->fetchColumn()<=1)throw new InvalidArgumentException('Debe quedar al menos una cuenta activa.');
                adminAudit($pdo,'cuenta_eliminada','cuenta',(string)$id,['usuario'=>$before['usuario'],'activo'=>(int)$before['activo']]);
                $pdo->prepare('DELETE FROM admin_usuarios WHERE id=?')->execute([$id]);
                $message='Cuenta eliminada.';
            }
        }
        $pdo->commit();notice($message);go('/cuentas.php');
    }catch(Throwable $e){
        if($pdo->inTransaction())$pdo->rollBack();
        $failure=adminAuditFailure($pdo,$action!==''?$action:'accion_desconocida','cuenta',$id?(string)$id:null,$e,['usuario_objetivo'=>$username]);
        if($e instanceof PDOException)$error=($e->errorInfo[1]??null)===1062?'Ese usuario o correo ya existe. Referencia: '.$failure['reference'].'.':'No se pudo actualizar la cuenta. Referencia: '.$failure['reference'].'.';
        else $error=$e->getMessage().' Referencia: '.$failure['reference'].'.';
    }
}

$emailColumn=$emailAvailable?'correo':'NULL AS correo';
$accounts=$pdo->query("SELECT id,usuario,$emailColumn,activo,creado_en FROM admin_usuarios ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
adminStart('Usuarios','usuarios','Cuentas administrativas y acceso seguro');
?>
<?php if($error):?><div class="alert error" role="alert"><?=h($error)?></div><?php endif;?>
<?php if(!$emailAvailable):?><div class="alert warning" role="status">Aplica la migracion del panel para administrar correos.</div><?php endif;?>
<?php if($emailAvailable):?><section class="card create-user-card"><div class="section-head compact"><div><h2>Crear usuario</h2><p>Las cuentas nuevas acceden al panel solo despues de autenticarse.</p></div></div><form class="form-grid user-create-form" method="post"><input type="hidden" name="csrf" value="<?=h(Csrf::token())?>"><input type="hidden" name="action" value="create"><label>Usuario<input name="usuario" required minlength="3" maxlength="80" pattern="[A-Za-z0-9._-]+" autocomplete="username"></label><label>Correo electronico<input type="email" name="correo" required maxlength="254" autocomplete="email"></label><label>Contrasena inicial<input type="password" name="password" required minlength="12" maxlength="128" autocomplete="new-password"></label><div class="form-action"><button class="button primary" type="submit">Crear usuario</button></div></form></section><?php endif;?>
<div class="section-head"><div><h2>Usuarios existentes</h2><p>La cuenta <code>admin</code> es permanente y su correo no se puede modificar.</p></div></div>
<section class="account-grid">
<?php foreach($accounts as $account):$own=(int)$account['id']===Auth::id();$protected=strcasecmp((string)$account['usuario'],'admin')===0;?>
<article class="card account-card <?=$own?'current-account':''?>"><header><div><h3><?=h($account['usuario'])?></h3><p><span class="badge <?=$account['activo']?'ok':'off'?>"><?=$account['activo']?'Activa':'Inactiva'?></span><?php if($own):?> <span class="badge current">Cuenta actual</span><?php endif;?><?php if($protected):?> <span class="badge warn">Protegida</span><?php endif;?></p></div><small>Creada: <?=h($account['creado_en'])?></small></header>
<?php if($protected):?><p class="muted"><strong>Correo no modificable.</strong> Esta restriccion tambien se valida en el servidor.</p><p><?=h((string)($account['correo']??'Sin correo configurado'))?></p><?php elseif($emailAvailable):?><form class="form-stack" method="post"><input type="hidden" name="csrf" value="<?=h(Csrf::token())?>"><input type="hidden" name="action" value="update"><input type="hidden" name="id" value="<?=(int)$account['id']?>"><label>Usuario<input name="usuario" required minlength="3" maxlength="80" pattern="[A-Za-z0-9._-]+" value="<?=h($account['usuario'])?>"></label><label>Correo electronico<input type="email" name="correo" required maxlength="254" value="<?=h((string)$account['correo'])?>"></label><label class="check"><input type="checkbox" name="activo" value="1" <?=$account['activo']?'checked':''?> <?=$own?'disabled':''?>> Cuenta activa</label><?php if($own):?><input type="hidden" name="activo" value="1"><?php endif;?><button class="button primary" type="submit">Guardar cambios</button></form><?php endif;?>
<?php if($own&&(int)$account['activo']===1):?><form class="form-stack password-form" method="post"><input type="hidden" name="csrf" value="<?=h(Csrf::token())?>"><input type="hidden" name="action" value="change_password"><input type="hidden" name="id" value="<?=(int)$account['id']?>"><h4>Cambiar mi contrasena</h4><label>Contrasena actual<input type="password" name="current_password" required autocomplete="current-password"></label><label>Nueva contrasena<input type="password" name="password" required minlength="12" maxlength="128" autocomplete="new-password"></label><button class="button ghost" type="submit">Cambiar contrasena</button></form><?php endif;?>
<?php if(!$protected):?><div class="actions account-actions"><form method="post"><input type="hidden" name="csrf" value="<?=h(Csrf::token())?>"><input type="hidden" name="action" value="revoke"><input type="hidden" name="id" value="<?=(int)$account['id']?>"><button class="button ghost small" type="submit">Revocar sesiones</button></form><?php if(!$own):?><form method="post" data-confirm="¿Eliminar permanentemente la cuenta <?=h($account['usuario'])?>?"><input type="hidden" name="csrf" value="<?=h(Csrf::token())?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=(int)$account['id']?>"><button class="button danger small" type="submit">Eliminar cuenta</button></form><?php endif;?></div><?php endif;?>
</article><?php endforeach;?>
</section>
<?php adminEnd();
