<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

$indexPath = dirname(__DIR__) . '/index.php';
if (!is_file($indexPath)) {
    echo "index.php not found at $indexPath";
    exit;
}

$html = file_get_contents($indexPath);
if ($html === false) {
    echo "Failed to read index.php";
    exit;
}

$insertedGallery = 0;
$insertedMenu = 0;

// Normalize helper
function normalizePath($p) {
    $p = trim($p);
    if ($p === '') return '';
    $p = str_replace('\\', '/', $p);
    if ($p[0] !== '/') $p = '/' . ltrim($p, '/');
    return $p;
}

/* === Import gallery images === */
if (preg_match_all('#<img[^>]+src=[\'"]([^\'"]*/assets/gallery/[^\'"]+)[\'"][^>]*>#i', $html, $m)) {
    $imgs = array_unique($m[1]);
    $ins = $pdo->prepare("SELECT id FROM gallery WHERE img = ? LIMIT 1");
    $add = $pdo->prepare("INSERT INTO gallery (img, caption, created_at) VALUES (?, ?, NOW())");
    foreach ($imgs as $src) {
        $src = normalizePath($src);
        $ins->execute([$src]);
        if (!$ins->fetch(PDO::FETCH_ASSOC)) {
            $add->execute([$src, null]);
            $insertedGallery++;
        }
    }
}

/* === Import menu items ===
   looks for blocks with classes used by frontend: mm-item / mm-name / mm-desc / mm-price
*/
$menuBlocks = [];
if (preg_match_all('#<div[^>]*class=["\'][^"\']*mm-item[^"\']*["\'][^>]*>(.*?)</div>#is', $html, $mb)) {
    $menuBlocks = $mb[1];
} else {
    // fallback: look for mm-name occurrences and capture surrounding HTML lines
    if (preg_match_all('#<div[^>]*class=["\']mm-name["\'][^>]*>(.*?)</div>#is', $html, $names)) {
        foreach ($names[0] as $i => $full) {
            $menuBlocks[] = $full;
        }
    }
}

if (!empty($menuBlocks)) {
    $sel = $pdo->prepare("SELECT id FROM menu WHERE name = ? LIMIT 1");
    $insMenu = $pdo->prepare("INSERT INTO menu (name, description, price, img, active, created_at) VALUES (?, ?, ?, ?, 1, NOW())");
    foreach ($menuBlocks as $block) {
        // extract name
        $name = null;
        if (preg_match('#<div[^>]*class=["\'][^"\']*mm-name[^"\']*["\'][^>]*>(.*?)</div>#is', $block, $nm)) {
            $name = trim(strip_tags($nm[1]));
        } elseif (preg_match('#<h3[^>]*>(.*?)</h3>#is', $block, $h)) {
            $name = trim(strip_tags($h[1]));
        }
        if (!$name) continue;

        // description
        $desc = '';
        if (preg_match('#<div[^>]*class=["\'][^"\']*mm-desc[^"\']*["\'][^>]*>(.*?)</div>#is', $block, $d)) {
            $desc = trim(strip_tags($d[1]));
        } elseif (preg_match('#<p[^>]*>(.*?)</p>#is', $block, $p)) {
            $desc = trim(strip_tags($p[1]));
        }

        // price
        $price = '0.00';
        if (preg_match('#<div[^>]*class=["\'][^"\']*mm-price[^"\']*["\'][^>]*>[^0-9\-\.]*([0-9\.,]+)#is', $block, $pr)) {
            $pnum = str_replace(',', '', $pr[1]);
            if (is_numeric($pnum)) $price = number_format((float)$pnum, 2, '.', '');
        } else {
            // try common ₹ or Rs. patterns
            if (preg_match('#₹\s*([0-9\.,]+)#u', $block, $pr2)) {
                $pnum = str_replace(',', '', $pr2[1]);
                if (is_numeric($pnum)) $price = number_format((float)$pnum, 2, '.', '');
            }
        }

        // img (optional)
        $img = null;
        if (preg_match('#<img[^>]+src=[\'"]([^\'"]+)[\'"][^>]*>#i', $block, $im)) {
            $img = normalizePath($im[1]);
        }

        // check exists
        $sel->execute([$name]);
        if (!$sel->fetch(PDO::FETCH_ASSOC)) {
            $insMenu->execute([$name, $desc ?: null, $price, $img ?: null]);
            $insertedMenu++;
        }
    }
}

?>
<!doctype html>
<html>
<head><meta charset="utf-8"><title>Import Results</title></head>
<body style="font-family:Inter,Arial,sans-serif;padding:18px">
  <h2>Import Results</h2>
  <p>Gallery images inserted: <?php echo (int)$insertedGallery; ?></p>
  <p>Menu items inserted: <?php echo (int)$insertedMenu; ?></p>
  <p><a href="gallery.php">Back to Gallery</a> · <a href="menu.php">Back to Menu</a></p>
</body>
</html>