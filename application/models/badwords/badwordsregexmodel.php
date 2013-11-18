<?php
require_once(dirname(__FILE__) .'/BadWordsRegex.php');
require_once(COREPATH . 'models/staticmodel.php');

class BadWordsRegexModel extends StaticModel {

    protected $tbl_name = 'badword_regex';
    private $cache_expiry_time = 900;
    function BadWordsRegexModel() {
        parent::StaticModel("sharded_db");
    }

    public function create() {
        $obj = new BadWordsRegex();
        return $obj;
    }

    public function load_all($get_all = true) {
        $regexes = NULL;
        if ($get_all) {
            $regexes = parent::get_all($this->cache_expiry_time);
        } else {
            $regexes = parent::get_where(array('is_available' => 1), $this->cache_expiry_time);
        }
        return $regexes;
    }

    public function load_all_disabled() {
        $items = parent::get_where(array('is_available' => 0));
        return $items;
    }

    public function remove_by_id($id) {
        parent::delete(array('id' => $id));
    }

    public function load_by_id($id) {
        $items = parent::get_where_nocache((array('id' => $id)));
        if (count($items) > 0) {
            return $items[0];
        }
    }

    public function add($regex, $is_available) {
        $bad_word = $this->create();

        $bad_word->is_available = $is_available;
        $bad_word->regex = $regex;

        parent::save($bad_word, True);
    }

    public function load_for_admin(){
        return parent::get_where_nocache(array());
    }
    
    public function edit_by_id($id, $new_regex, $new_ava) {
        $items = parent::get_where_nocache(array('id' => $id));
        if (count($items) > 0) {
            $item = $items[0];
            $item->regex = $new_regex;
            $item->is_available = $new_ava;
            parent::save($item);
        }
    }


}
