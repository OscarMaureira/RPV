<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
if(Auth::user())adminAuditRequestStart($pdo);
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);exit('Metodo no permitido.');}
Csrf::validate($_POST['csrf']??null); Auth::logout($pdo); go('/login.php');
