<?php
declare(strict_types=1);
final class DocumentSequence{
 public static function next(PDO $db,string $type,int $year,string $prefix):string{
  $db->prepare('INSERT INTO document_sequences(document_type,year_no,next_number,prefix) VALUES(?,?,2,?) ON DUPLICATE KEY UPDATE next_number=LAST_INSERT_ID(next_number+1),prefix=VALUES(prefix)')->execute([$type,$year,$prefix]);
  $n=(int)$db->lastInsertId();if($n===0)$n=1;
  return $prefix.'-'.$year.'-'.str_pad((string)$n,6,'0',STR_PAD_LEFT);
 }
}
