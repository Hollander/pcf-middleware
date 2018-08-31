<?php

include_once( dirname(__FILE__) . "/Config.php");
include_once( dirname(__FILE__) . "/Helper.php");
include_once( dirname(__FILE__) . "/Log.php");
include_once( dirname(__FILE__) . "/Connections.php");

class Queue {

    static function prepare($sql) {
        $mysql  = Connections::Instance()->getMySqlConnection();

        $stm = $mysql->prepare($sql);
        if ($stm)
        return $stm;
        else {
            Log::error("PDO::errorInfo():");
            return false;
        }
    }

    static function enqueue($type, $data) {
        $task = array(
            'id'         => Helper::getUID(16),
            'type'       => $type,
            'enqueued'   => date('Y-m-d H:i:s'),
            'dequeued'   => null,
            'message'    => json_encode($data),
            'status'     => null
        );

        try {
            // check for existing
            $stm = Queue::prepare('SELECT * FROM queue WHERE `type` = :type AND `dequeued` IS NULL AND `message` = :message');
            if (!$stm) { return false; }

            $stm->execute(array(
                'type'      => $task['type'],
                'message'   => $task['message']
            ));
            $match = $stm->fetchAll();

            if (!$match) {
                $stm = Queue::prepare('INSERT INTO queue (`id`, `type`, `enqueued`, `dequeued`, `message`, `status`) VALUES (:id, :type, :enqueued, :dequeued, :message, :status)');
                if (!$stm) { return false; }

                $stm->execute($task);
            } else {
                Log::error("Task already exists in queue: ".$task['type']." ".$task['message']);
            }
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return false;
        }

        return $task;
    }

    static function dequeue($id) {
        try {
            if (is_array($id)) {
                if (count($id) > 0) {
                    $list = join(',', array_fill(0, count($id), '?'));
                    $stm = Queue::prepare('UPDATE queue SET `dequeued` = NOW() WHERE `id` IN ('.$list.')');
                    if (!$stm) { return false; }

                    $stm->execute(array_values($id));
                }
            } else {
                $stm = Queue::prepare('UPDATE queue SET `dequeued` = NOW() WHERE `id` = :id');
                if (!$stm) { return false; }

                $stm->execute(array( 'id' => $id ));
            }
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return false;
        }

        return true;
    }

    static function status($id, $status) {
        try {
            $stm = Queue::prepare('UPDATE queue SET `status` = :status WHERE `id` = :id');
            if (!$stm) { return false; }

            $stm->execute(array(
                'id'         => $id,
                'status'     => $status
            ));
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return false;
        }

        return true;
    }

    static function process($type) {
        try {
            $stm = Queue::prepare('SELECT * FROM queue WHERE `type` = :type AND `dequeued` IS NULL');
            if (!$stm) { return false; }

            $stm->execute(array(
                'type' => $type
            ));
            $jobs = $stm->fetchAll();

            if ($jobs && count($jobs) > 0) {
                $ids = array();
                foreach($jobs as $job) {
                    $ids[] = $job['id'];
                }

                $list = join(',', array_fill(0, count($ids), '?'));
                $stm = Queue::prepare('UPDATE queue SET attempts = attempts + 1 WHERE `id` IN ('.$list.')');
                if (!$stm) { return false; }

                $stm->execute(array_values($ids));
            }

            return $jobs;
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return false;
        }
    }

}

?>
