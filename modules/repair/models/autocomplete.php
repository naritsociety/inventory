<?php
/**
 * @filesource modules/repair/models/autocomplete.php
 *
 * @copyright 2016 Goragod.com
 * @license http://www.kotchasan.com/license/
 *
 * @see http://www.kotchasan.com/
 */

namespace Repair\Autocomplete;

use Gcms\Login;
use Kotchasan\Http\Request;

/**
 * ค้นหาครุภัณฑ์สำหรับ autocomplete.
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * ค้นหาครุภัณฑ์สำหรับ autocomplete
     * คืนค่าเป็น JSON
     *
     * @param Request $request
     */
    public function find(Request $request)
    {
        if ($request->initSession() && $request->isReferer() && Login::isMember()) {
            try {
                // ข้อมูลที่ส่งมา
                if ($request->post('topic')->exists()) {
                    $search = $request->post('topic')->topic();
                    $order = 'topic';
                } elseif ($request->post('product_no')->exists()) {
                    $search = $request->post('product_no')->topic();
                    $order = 'product_no';
                }
                // query
                $query = $this->db()->createQuery()
                    ->select('id inventory_id', 'topic', 'product_no')
                    ->from('inventory')
                    ->limit($request->post('count', 20)->toInt())
                    ->toArray();
                if (isset($search)) {
                    $query->where(array($order, 'LIKE', "%$search%"))->order($order);
                }
                if (isset($order)) {
                    $query->order($order);
                }
                $result = $query->execute();
                if (!empty($result)) {
                    // คืนค่า JSON
                    echo json_encode($result);
                }
            } catch (\Kotchasan\InputItemException $e) {
            }
        }
    }
}
