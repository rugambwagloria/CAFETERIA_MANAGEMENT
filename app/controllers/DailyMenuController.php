<?php

declare(strict_types=1);

class DailyMenuController
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function handlePost(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        $action = $_POST['action'] ?? '';

        if ($action === 'toggle_stock' && isset($_POST['item_id'])) {
            $stmt = $this->pdo->prepare('UPDATE food_items SET in_stock = NOT in_stock WHERE item_id = ?');
            $stmt->execute([(int)$_POST['item_id']]);
        }

        if ($action === 'delete_item' && isset($_POST['item_id'])) {
            $stmt = $this->pdo->prepare('DELETE FROM food_items WHERE item_id = ?');
            $stmt->execute([(int)$_POST['item_id']]);
        }

        // Redirect so a page refresh never repeats the POST.
        $qs = $_GET ? '?' . http_build_query($_GET) : '';
        header('Location: ' . basename($_SERVER['PHP_SELF']) . $qs);
        exit;
    }

    /**
     * Fetch menu, items and prices grouped by item.
     * Returns array: [menu, items, pricesByItem]
     */
    public function getMenuData(string $menuDate, string $activeTab): array
    {
        $items = [];
        $pricesByItem = [];
        $menu = null;
        $error = null;

        try {
            $menuStmt = $this->pdo->prepare('SELECT menu_id, menu_date FROM daily_menus WHERE menu_date = ?');
            $menuStmt->execute([$menuDate]);
            $menu = $menuStmt->fetch(\PDO::FETCH_ASSOC);

            if ($menu) {
                $sql = 'SELECT item_id, name, meal_type, base_price, in_stock
                        FROM food_items
                        WHERE menu_id = :menu_id';
                $params = ['menu_id' => $menu['menu_id']];

                if ($activeTab !== 'all') {
                    $sql .= ' AND meal_type = :meal_type';
                    $params['meal_type'] = $activeTab;
                }

                $sql .= ' ORDER BY FIELD(meal_type, "breakfast","lunch","dinner","drinks"), item_id';

                $stmt = $this->pdo->prepare($sql);
                $stmt->execute($params);
                $items = $stmt->fetchAll(\PDO::FETCH_ASSOC);

                if ($items) {
                    $itemIds = array_column($items, 'item_id');
                    $in = implode(',', array_fill(0, count($itemIds), '?'));

                    $priceSql =
                        "SELECT icp.item_id, cc.category_id, cc.name AS category_name, icp.price
                         FROM item_category_prices icp
                         JOIN customer_categories cc ON cc.category_id = icp.category_id
                         WHERE icp.item_id IN ($in)
                         ORDER BY cc.category_id";

                    $priceStmt = $this->pdo->prepare($priceSql);
                    $priceStmt->execute($itemIds);

                    foreach ($priceStmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                        $pricesByItem[$row['item_id']][] = $row;
                    }
                }
            }
        } catch (\PDOException $e) {
            // Likely the table doesn't exist or another DB error; surface a friendly message to the page.
            $error = $e->getMessage();
            $menu = null;
            $items = [];
            $pricesByItem = [];
        }

        return [$menu, $items, $pricesByItem, $error];
    }
}
