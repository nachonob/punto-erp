<?php
declare(strict_types=1);

/**
 * Unifica las variantes históricas de la categoría de productos LifeSmart.
 * Es idempotente: puede ejecutarse en cada acceso sin volver a modificar datos.
 */
function consolidateLifeSmartDomoticsCategory(PDO $db): void
{
    $normalize=static function(string $value):string{
        $value=mb_strtolower(trim($value),'UTF-8');
        $value=strtr($value,['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u']);
        return preg_replace('/[^a-z0-9]+/','',$value)??'';
    };

    $accepted=['domoticalifesmart','lifesmartdomotica','domoticalivesmart','livesmartdomotica'];
    $categories=$db->query('SELECT id,name FROM product_categories ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    $matches=[];
    foreach($categories as $category){
        if(in_array($normalize((string)$category['name']),$accepted,true))$matches[]=$category;
    }
    if(!$matches)return;

    $target=null;
    foreach($matches as $category){
        if($normalize((string)$category['name'])==='domoticalifesmart'){$target=$category;break;}
    }
    $target??=$matches[0];
    $targetId=(int)$target['id'];

    $db->beginTransaction();
    try{
        $hasRules=(bool)$db->query("SHOW TABLES LIKE 'product_category_price_rules'")->fetchColumn();
        foreach($matches as $category){
            $duplicateId=(int)$category['id'];
            if($duplicateId===$targetId)continue;

            if($hasRules){
                $rules=$db->prepare('SELECT price_list_id,percentage FROM product_category_price_rules WHERE category_id=?');
                $rules->execute([$duplicateId]);
                foreach($rules as $rule){
                    $exists=$db->prepare('SELECT 1 FROM product_category_price_rules WHERE price_list_id=? AND category_id=?');
                    $exists->execute([(int)$rule['price_list_id'],$targetId]);
                    if(!$exists->fetchColumn()){
                        $db->prepare('INSERT INTO product_category_price_rules(price_list_id,category_id,percentage) VALUES(?,?,?)')
                           ->execute([(int)$rule['price_list_id'],$targetId,(float)$rule['percentage']]);
                    }
                }
                $db->prepare('DELETE FROM product_category_price_rules WHERE category_id=?')->execute([$duplicateId]);
            }

            $db->prepare('UPDATE products SET category_id=? WHERE category_id=?')->execute([$targetId,$duplicateId]);
            $db->prepare('DELETE FROM product_categories WHERE id=?')->execute([$duplicateId]);
        }

        $db->prepare("UPDATE product_categories SET name='Domótica LifeSmart',active=1 WHERE id=?")->execute([$targetId]);
        $db->prepare("UPDATE products SET brand='LifeSmart' WHERE category_id=? AND (brand IS NULL OR TRIM(brand)='' OR LOWER(REPLACE(brand,' ','')) IN ('livesmart','livesmart'))")->execute([$targetId]);
        $db->commit();
    }catch(Throwable $error){
        if($db->inTransaction())$db->rollBack();
        throw $error;
    }
}
