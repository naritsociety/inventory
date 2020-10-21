<?php
/**
 * @filesource modules/repair/models/receive.php
 *
 * @copyright 2016 Goragod.com
 * @license http://www.kotchasan.com/license/
 *
 * @see http://www.kotchasan.com/
 */

namespace Repair\Receive;

use Gcms\Login;
use Kotchasan\Http\Request;
use Kotchasan\Language;

/**
 * เพิ่ม-แก้ไข ใบแจ้งซ่อม
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * อ่านข้อมูลรายการที่เลือก
     * ถ้า $id = 0 หมายถึงรายการใหม่
     * คืนค่าข้อมูล object ไม่พบคืนค่า null
     *
     * @param int $id ID
     *
     * @return object|null
     */
    public static function get($id)
    {
        if (empty($id)) {
            // ใหม่
            return (object) array(
                'id' => 0,
                'topic' => '',
                'product_no' => '',
                'inventory_id' => 0,
                'job_description' => '',
                'comment' => '',
                'status_id' => 0,
            );
        } else {
            // แก้ไข
            return static::createQuery()
                ->from('repair R')
                ->join('inventory V', 'LEFT', array('V.id', 'R.inventory_id'))
                ->where(array('R.id', $id))
                ->first('R.*', 'V.topic', 'V.product_no');
        }
    }

    /**
     * บันทึกค่าจากฟอร์ม
     *
     * @param Request $request
     */
    public function submit(Request $request)
    {
        $ret = array();
        // session, token, member
        if ($request->initSession() && $request->isSafe() && $login = Login::isMember()) {
            try {
                // รับค่าจากการ POST
                $repair = array(
                    'job_description' => $request->post('job_description')->textarea(),
                    'inventory_id' => $request->post('inventory_id')->toInt(),
                    'appraiser' => 0,
                );
                $topic = $request->post('topic')->topic();
                $product_no = $request->post('product_no')->topic();
                // สามารถจัดการรายการซ่อมได้
                $can_manage_repair = Login::checkPermission($login, 'can_manage_repair');
                // ตรวจสอบรายการที่เลือก
                $index = self::get($request->post('id')->toInt());
                if (!$index || $index->id > 0 && ($login['id'] != $index->customer_id && !$can_manage_repair)) {
                    // ไม่พบรายการที่แก้ไข
                    $ret['alert'] = Language::get('Sorry, Item not found It&#39;s may be deleted');
                } elseif (empty($product_no)) {
                    // product_no
                    $ret['ret_product_no'] = 'Please fill in';
                } elseif (empty($topic)) {
                    // topic
                    $ret['ret_topic'] = 'Please fill in';
                } elseif (empty($repair['inventory_id'])) {
                    // ไม่พบรายการพัสดุที่เลือก
                    $ret['ret_topic'] = Language::get('Please select from the search results');
                } else {
                    // ตาราง
                    $repair_table = $this->getTableName('repair');
                    $repair_status_table = $this->getTableName('repair_status');
                    // Database
                    $db = $this->db();
                    if ($index->id == 0) {
                        // job_id
                        $repair['job_id'] = uniqid();
                        // ตรวจสอบ job_id ซ้ำ
                        while ($db->first($repair_table, array('job_id', $repair['job_id']))) {
                            $repair['job_id'] = uniqid();
                        }
                        $repair['customer_id'] = $login['id'];
                        $repair['create_date'] = date('Y-m-d H:i:s');
                        // บันทึกรายการแจ้งซ่อม
                        $log = array(
                            'repair_id' => $db->insert($repair_table, $repair),
                            'member_id' => $login['id'],
                            'comment' => $request->post('comment')->topic(),
                            'status' => isset(self::$cfg->repair_first_status) ? self::$cfg->repair_first_status : 1,
                            'create_date' => $repair['create_date'],
                            'operator_id' => 0,
                        );
                        // บันทึกประวัติการทำรายการ แจ้งซ่อม
                        $db->insert($repair_status_table, $log);
                        // ใหม่ ส่งอีเมลไปยังผู้ที่เกี่ยวข้อง
                        $ret['alert'] = \Repair\Email\Model::send($log['repair_id']);
                    } else {
                        // แก้ไขรายการแจ้งซ่อม
                        $db->update($repair_table, $index->id, $repair);
                        // คืนค่า
                        $ret['alert'] = Language::get('Saved successfully');
                    }
                    if ($can_manage_repair && $index->id > 0) {
                        // สามารถจัดการรายการซ่อมได้
                        $ret['location'] = $request->getUri()->postBack('index.php', array('module' => 'repair-setup', 'id' => null));
                    } else {
                        // ใหม่
                        $ret['location'] = $request->getUri()->postBack('index.php', array('module' => 'repair-history', 'id' => null));
                    }
                    // clear
                    $request->removeToken();
                }
            } catch (\Kotchasan\InputItemException $e) {
                $ret['alert'] = $e->getMessage();
            }
        }
        if (empty($ret)) {
            $ret['alert'] = Language::get('Unable to complete the transaction');
        }
        // คืนค่าเป็น JSON
        echo json_encode($ret);
    }
}
