<?php

class UpdateProductVariantsSchema
{
    public function up(\PDO $pdo = null): void
    {
        $pdo = $pdo ?? $this->pdo;
        // Add options_schema to products
        $pdo->exec("ALTER TABLE `products` ADD COLUMN `options_schema` LONGTEXT DEFAULT NULL AFTER `tags`");
        
        // Add multi-axis option columns, compare_price, and barcode to product_variants
        $pdo->exec("ALTER TABLE `product_variants` 
            ADD COLUMN `option1_value` VARCHAR(255) DEFAULT NULL AFTER `name`,
            ADD COLUMN `option2_value` VARCHAR(255) DEFAULT NULL AFTER `option1_value`,
            ADD COLUMN `option3_value` VARCHAR(255) DEFAULT NULL AFTER `option2_value`,
            ADD COLUMN `compare_price` DECIMAL(10,2) DEFAULT NULL AFTER `price_override`,
            ADD COLUMN `barcode` VARCHAR(100) DEFAULT NULL AFTER `sku`
        ");

        // Migrate existing variants: map color_name to option1 and size to option2
        // We'll build a default options_schema for products that have variants
        $products = $pdo->query("SELECT DISTINCT product_id FROM product_variants")->fetchAll(\PDO::FETCH_ASSOC);
        
        foreach ($products as $p) {
            $pid = (int)$p['product_id'];
            $variants = $pdo->query("SELECT * FROM product_variants WHERE product_id = {$pid}")->fetchAll(\PDO::FETCH_ASSOC);
            
            $hasColor = false;
            $hasSize = false;
            foreach ($variants as $v) {
                if (!empty($v['color_name'])) $hasColor = true;
                if (!empty($v['size'])) $hasSize = true;
            }
            
            $options = [];
            if ($hasColor) $options[] = ['name' => 'Color', 'values' => []];
            if ($hasSize) $options[] = ['name' => 'Size', 'values' => []];
            
            if (!empty($options)) {
                // Collect unique values
                foreach ($variants as $v) {
                    if ($hasColor && !empty($v['color_name'])) {
                        if (!in_array($v['color_name'], $options[0]['values'])) {
                            $options[0]['values'][] = $v['color_name'];
                        }
                    }
                    if ($hasSize && !empty($v['size'])) {
                        $idx = $hasColor ? 1 : 0;
                        if (!in_array($v['size'], $options[$idx]['values'])) {
                            $options[$idx]['values'][] = $v['size'];
                        }
                    }
                }
                
                $schemaJson = json_encode($options);
                $stmt = $pdo->prepare("UPDATE `products` SET `options_schema` = ? WHERE `id` = ?");
                $stmt->execute([$schemaJson, $pid]);
                
                // Update variant rows
                $updateStmt = $pdo->prepare("UPDATE `product_variants` SET `option1_value` = ?, `option2_value` = ? WHERE `id` = ?");
                foreach ($variants as $v) {
                    $opt1 = null;
                    $opt2 = null;
                    if ($hasColor) {
                        $opt1 = $v['color_name'];
                        if ($hasSize) $opt2 = $v['size'];
                    } else if ($hasSize) {
                        $opt1 = $v['size'];
                    }
                    
                    $updateStmt->execute([$opt1, $opt2, $v['id']]);
                }
            }
        }
    }

    public function down(\PDO $pdo = null): void
    {
        $pdo = $pdo ?? $this->pdo;
        $pdo->exec("ALTER TABLE `product_variants` 
            DROP COLUMN `option1_value`,
            DROP COLUMN `option2_value`,
            DROP COLUMN `option3_value`,
            DROP COLUMN `compare_price`,
            DROP COLUMN `barcode`
        ");
        $pdo->exec("ALTER TABLE `products` DROP COLUMN `options_schema`");
    }
}
