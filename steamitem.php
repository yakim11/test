<?php namespace App\Services;

use App\Http\Controllers\GameController;
use App\Http\Controllers\SteamController;
use Exception;
use Storage;

class SteamItem {

    const BANK_URL = 'http://www.cbr.ru/scripts/XML_daily.asp';

    const SteamAnalyst_Path = 'items/steamanalyst.txt';
    const BackPackTF_Path = 'items/backpacktf.txt';

    public  $classid;
    public  $name;
    public  $market_hash_name;
    public  $price;
    public  $rarity;

    public function __construct($info)
    {
        $this->classid = $info['classid'];
        $this->name = $info['name'];
        $this->market_hash_name = $info['market_hash_name'];
        $this->rarity = isset($info['rarity']) ? $info['rarity'] : $this->getItemRarity($info);
        $this->price = $this->getItemPrice();
    }

    /* PARSING FUNCTIONS */

    public static function SteamAnalyst()
    {
        $data = file_get_contents('http://steamp.ru/v2/?key=773fafdb627250ff3bf7da72b6cf2771&appid=570');

        if (Storage::disk('local')->exists(self::SteamAnalyst_Path))
        {
            Storage::disk('local')->delete(self::SteamAnalyst_Path);
        }

        if ($data) {
            Storage::disk('local')->put(self::SteamAnalyst_Path, $data);
            return true;
        }
        else
        {
            return false;
        }
    }

    public static function BackPackTF()
    {
        $data = file_get_contents('http://steamp.ru/v2/?key=773fafdb627250ff3bf7da72b6cf2771&appid=570');
        $response = json_decode($data);
        $success = $response->response->success;

        if (Storage::disk('local')->exists(self::BackPackTF_Path))
        {
            Storage::disk('local')->delete(self::BackPackTF_Path);
        }

        if ($success !== 0) {
            Storage::disk('local')->put(self::BackPackTF_Path, $data);
            return 'Successfully Parsing BackPackTF';
        } else {
            $message = $response->response->message;
            return $message;
        }
    }

    /* PARSING FUNCTIONS END */

    public function getItemPrice() {
        try{
            if (Storage::disk('local')->exists(self::SteamAnalyst_Path))
            {
                $json = Storage::get(self::SteamAnalyst_Path);
                $items = json_decode($json);

                $usd = $this->getActualCurs();
                $item_name = $this->market_hash_name;

                $item = $items->$item_name;
                $price_item = $item * $usd;

                return $price_item;
            }
            else {
                $json = Storage::get(self::BackPackTF_Path);
                $items = json_decode($json);

                $usd = $this->getActualCurs();
                $item_name = $this->market_hash_name;

                if ($items->response->items->$item_name == 'undefined') {
                    return false;
                } else {
                    $item = $items->response->items->$item_name->value;
                    $price_item = $item / 100 * $usd;

                    return $price_item;
                }
            }
        }catch(Exception $e){
            return false;
        }
    }

    public function getActualCurs() {
        $link = self::BANK_URL;
        $str  = file_get_contents($link);

        preg_match('#<Valute ID="R01235">.*?.<Value>(.*?)</Value>.*?</Valute>#is', $str, $value);

        $usd = $value[1];

        return $usd;
    }

    public function getItemRarity($info) {
        $type = $info['type'];
        $rarity = '';
        $arr = explode(',',$type);
        if (count($arr) == 2) $type = trim($arr[1]);
        if (count($arr) == 3) $type = trim($arr[2]);
        if (count($arr) && $arr[0] == 'Нож') $type = '★';
        switch ($type) {
            case 'Армейское качество':      $rarity = 'milspec'; break;
            case 'Запрещенное':             $rarity = 'restricted'; break;
            case 'Засекреченное':           $rarity = 'classified'; break;
            case 'Тайное':                  $rarity = 'covert'; break;
            case 'Ширпотреб':               $rarity = 'common'; break;
            case 'Промышленное качество':   $rarity = 'common'; break;
            case '★':                       $rarity = 'rare'; break;
        }

        return $rarity;
    }

    private function _setToFalse()
    {
        $this->name = false;
        $this->price = false;
        $this->rarity = false;
    }
}
