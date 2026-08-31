<?php
declare(strict_types=1);
final class Auth{
 public static function user():?array{return $_SESSION['user']??null;}
 public static function check():bool{return isset($_SESSION['user']);}
 public static function isAdmin():bool{return ($_SESSION['user']['role']??'')==='admin';}
 public static function requireLogin():void{if(!self::check()){header('Location: index.php');exit;}}
}
