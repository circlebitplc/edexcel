<?php
declare(strict_types=1);
$code=(int)($_GET['code']??http_response_code()?:500);
if(!in_array($code,[403,404,419,429,500,503],true))$code=500;
http_response_code($code);
$titles=[403=>'Access denied',404=>'Page not found',419=>'Session expired',429=>'Too many requests',500=>'Something went wrong',503=>'Service unavailable'];
$messages=[403=>'You do not have permission to open this page.',404=>'The requested page could not be found.',419=>'Your session has expired. Please sign in again.',429=>'Please wait a moment and try again.',500=>'The portal encountered an unexpected error.',503=>'The portal is temporarily unavailable.'];
$isApi=str_contains((string)($_SERVER['REQUEST_URI']??''),'/api/')||str_contains(strtolower((string)($_SERVER['HTTP_ACCEPT']??'')),'application/json');
if($isApi){header('Content-Type: application/json; charset=utf-8');echo json_encode(['ok'=>false,'error'=>['code'=>$code,'message'=>$messages[$code]]]);exit;}
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex, follow"><title><?=htmlspecialchars($code.' · '.$titles[$code])?></title><link href="/assets/css/system.css" rel="stylesheet"></head><body><main style="min-height:100vh;display:grid;place-items:center;padding:24px"><section style="max-width:560px;text-align:center"><div style="font-size:5rem;font-weight:800"><?= $code?></div><h1><?=htmlspecialchars($titles[$code])?></h1><p><?=htmlspecialchars($messages[$code])?></p><p><a class="btn btn-primary" href="/">Return to homepage</a> <a class="btn btn-outline-secondary" href="/contact">Contact</a> <a class="btn btn-outline-secondary" href="/login.php">Staff login</a></p></section></main></body></html>
