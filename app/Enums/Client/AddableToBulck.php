<?php
namespace App\Enums\Client;
 enum AddableToBulck: int{

    case ADDABLE = 1;
    case NOTADDABLE = 0;

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
?>
