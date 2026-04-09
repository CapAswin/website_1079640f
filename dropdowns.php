<?php
/**
 * Simple JSON API for form dropdowns: country, province, category, niche.
 * GET ?type=countries
 * GET ?type=provinces&country_id=1
 * GET ?type=categories
 * GET ?type=niches&category_id=1
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

require_once __DIR__ . '/db.php';

$out = ['success' => false, 'data' => []];

if (!isset($conn) || !($conn instanceof mysqli) || $conn->connect_error) {
    echo json_encode($out);
    exit;
}

$type = isset($_GET['type']) ? trim($_GET['type']) : '';

switch ($type) {
    case 'countries':
        $res = @$conn->query("SELECT country_id, country_name FROM country ORDER BY country_name");
        if ($res && $res->num_rows >= 0) {
            while ($row = $res->fetch_assoc()) {
                $out['data'][] = ['id' => (int)$row['country_id'], 'name' => (string)$row['country_name']];
            }
            if ($res) $res->free();
            $out['success'] = true;
        } else {
            $out['error'] = $conn->error ?: 'Query failed';
        }
        break;

    case 'provinces':
        $country_id = isset($_GET['country_id']) ? (int)$_GET['country_id'] : 0;
        if ($country_id > 0) {
            $stmt = $conn->prepare("SELECT province_id, country_id, province_name FROM province WHERE country_id = ? ORDER BY province_name");
            $stmt->bind_param('i', $country_id);
            if ($stmt->execute()) {
                $res = $stmt->get_result();
                while ($row = $res->fetch_assoc()) {
                    $out['data'][] = ['id' => (int)$row['province_id'], 'country_id' => (int)$row['country_id'], 'name' => $row['province_name']];
                }
                $res->free();
                $out['success'] = true;
            }
            $stmt->close();
        } else {
            $out['success'] = true;
        }
        break;

    case 'categories':
        $res = $conn->query("SELECT category_id, category_name FROM category WHERE status = 1 ORDER BY category_name");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $out['data'][] = ['id' => (int)$row['category_id'], 'name' => $row['category_name']];
            }
            $res->free();
            $out['success'] = true;
        }
        break;

    case 'niches':
        $category_id = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
        if ($category_id > 0) {
            $stmt = $conn->prepare("SELECT niche_id, category_id, niche_name FROM niche WHERE status = 1 AND category_id = ? ORDER BY niche_name");
            $stmt->bind_param('i', $category_id);
            if ($stmt->execute()) {
                $res = $stmt->get_result();
                while ($row = $res->fetch_assoc()) {
                    $out['data'][] = ['id' => (int)$row['niche_id'], 'category_id' => (int)$row['category_id'], 'name' => $row['niche_name']];
                }
                $res->free();
                $out['success'] = true;
            }
            $stmt->close();
        } else {
            $out['success'] = true;
        }
        break;

    default:
        $out['error'] = 'Invalid or missing type. Use type=countries|provinces|categories|niches';
}

echo json_encode($out);
