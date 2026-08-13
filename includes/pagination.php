<?php
// includes/pagination.php
// Pagination helper functions

function paginate($total_items, $items_per_page = 50, $current_page = null)
{
    $current_page = $current_page ?: (int)($_GET['page'] ?? 1);
    $current_page = max(1, $current_page);
    
    $total_pages = ceil($total_items / $items_per_page);
    $offset = ($current_page - 1) * $items_per_page;
    
    return [
        'current_page' => $current_page,
        'total_pages' => $total_pages,
        'offset' => $offset,
        'limit' => $items_per_page,
        'total_items' => $total_items
    ];
}

function render_pagination($pagination, $url_params = [])
{
    if ($pagination['total_pages'] <= 1) return '';
    
    $html = '<nav><ul class="pagination justify-content-center">';
    $current = $pagination['current_page'];
    $total = $pagination['total_pages'];
    
    // Previous
    if ($current > 1) {
        $params = array_merge($url_params, ['page' => $current - 1]);
        $html .= '<li class="page-item"><a class="page-link" href="?' . http_build_query($params) . '">&laquo; Prev</a></li>';
    } else {
        $html .= '<li class="page-item disabled"><a class="page-link">&laquo; Prev</a></li>';
    }
    
    // First page
    if ($current > 3) {
        $params = array_merge($url_params, ['page' => 1]);
        $html .= '<li class="page-item"><a class="page-link" href="?' . http_build_query($params) . '">1</a></li>';
        if ($current > 4) {
            $html .= '<li class="page-item disabled"><a class="page-link">...</a></li>';
        }
    }
    
    // Range around current
    $start = max(1, $current - 2);
    $end = min($total, $current + 2);
    for ($i = $start; $i <= $end; $i++) {
        $params = array_merge($url_params, ['page' => $i]);
        $active = ($i == $current) ? ' active' : '';
        $html .= '<li class="page-item' . $active . '"><a class="page-link" href="?' . http_build_query($params) . '">' . $i . '</a></li>';
    }
    
    // Last page
    if ($current < $total - 2) {
        if ($current < $total - 3) {
            $html .= '<li class="page-item disabled"><a class="page-link">...</a></li>';
        }
        $params = array_merge($url_params, ['page' => $total]);
        $html .= '<li class="page-item"><a class="page-link" href="?' . http_build_query($params) . '">' . $total . '</a></li>';
    }
    
    // Next
    if ($current < $total) {
        $params = array_merge($url_params, ['page' => $current + 1]);
        $html .= '<li class="page-item"><a class="page-link" href="?' . http_build_query($params) . '">Next &raquo;</a></li>';
    } else {
        $html .= '<li class="page-item disabled"><a class="page-link">Next &raquo;</a></li>';
    }
    
    $html .= '</ul></nav>';
    return $html;
}