<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class Admin1CodesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run(): void
    {
        // Очищаем таблицу перед заполнением
        DB::table('admin1_codes')->truncate();

        // Официальные данные GeoNames для Admin1 Codes России (RU)
        // Источник: http://download.geonames.org/export/dump/admin1CodesASCII.txt
        $data = [
            ['country_code' => 'RU', 'admin1_code' => '01', 'name' => 'Республика Адыгея'],
            ['country_code' => 'RU', 'admin1_code' => '02', 'name' => 'Агинский Бурятский автономный округ'],
            ['country_code' => 'RU', 'admin1_code' => '03', 'name' => 'Республика Алтай'],
            ['country_code' => 'RU', 'admin1_code' => '04', 'name' => 'Алтайский край'],
            ['country_code' => 'RU', 'admin1_code' => '05', 'name' => 'Амурская область'],
            ['country_code' => 'RU', 'admin1_code' => '06', 'name' => 'Архангельская область'],
            ['country_code' => 'RU', 'admin1_code' => '07', 'name' => 'Астраханская область'],
            ['country_code' => 'RU', 'admin1_code' => '08', 'name' => 'Республика Башкортостан'],
            ['country_code' => 'RU', 'admin1_code' => '09', 'name' => 'Белгородская область'],
            ['country_code' => 'RU', 'admin1_code' => '10', 'name' => 'Брянская область'],
            ['country_code' => 'RU', 'admin1_code' => '11', 'name' => 'Республика Бурятия'],
            ['country_code' => 'RU', 'admin1_code' => '12', 'name' => 'Чеченская Республика'],
            ['country_code' => 'RU', 'admin1_code' => '13', 'name' => 'Челябинская область'],
            ['country_code' => 'RU', 'admin1_code' => '14', 'name' => 'Читинская область'],
            ['country_code' => 'RU', 'admin1_code' => '15', 'name' => 'Чукотский автономный округ'],
            ['country_code' => 'RU', 'admin1_code' => '16', 'name' => 'Чувашская Республика'],
            ['country_code' => 'RU', 'admin1_code' => '17', 'name' => 'Республика Дагестан'],
            ['country_code' => 'RU', 'admin1_code' => '19', 'name' => 'Республика Ингушетия'],
            ['country_code' => 'RU', 'admin1_code' => '20', 'name' => 'Иркутская область'],
            ['country_code' => 'RU', 'admin1_code' => '21', 'name' => 'Ивановская область'],
            ['country_code' => 'RU', 'admin1_code' => '22', 'name' => 'Кабардино‑Балкарская Республика'],
            ['country_code' => 'RU', 'admin1_code' => '23', 'name' => 'Калининградская область'],
            ['country_code' => 'RU', 'admin1_code' => '24', 'name' => 'Калмыкия, Республика'],
            ['country_code' => 'RU', 'admin1_code' => '25', 'name' => 'Калужская область'],
            ['country_code' => 'RU', 'admin1_code' => '27', 'name' => 'Карачаево‑Черкесская Республика'],
            ['country_code' => 'RU', 'admin1_code' => '28', 'name' => 'Республика Карелия'],
            ['country_code' => 'RU', 'admin1_code' => '29', 'name' => 'Кемеровская область'],
            ['country_code' => 'RU', 'admin1_code' => '30', 'name' => 'Хабаровский край'],
            ['country_code' => 'RU', 'admin1_code' => '31', 'name' => 'Республика Хакасия'],
            ['country_code' => 'RU', 'admin1_code' => '32', 'name' => 'Ханты‑Мансийский автономный округ'],
            ['country_code' => 'RU', 'admin1_code' => '33', 'name' => 'Кировская область'],
            ['country_code' => 'RU', 'admin1_code' => '34', 'name' => 'Республика Коми'],
            ['country_code' => 'RU', 'admin1_code' => '37', 'name' => 'Костромская область'],
            ['country_code' => 'RU', 'admin1_code' => '38', 'name' => 'Краснодарский край'],
            ['country_code' => 'RU', 'admin1_code' => '40', 'name' => 'Курганская область'],
            ['country_code' => 'RU', 'admin1_code' => '41', 'name' => 'Курская область'],
            ['country_code' => 'RU', 'admin1_code' => '42', 'name' => 'Ленинградская область'],
            ['country_code' => 'RU', 'admin1_code' => '43', 'name' => 'Липецкая область'],
            ['country_code' => 'RU', 'admin1_code' => '44', 'name' => 'Магаданская область'],
            ['country_code' => 'RU', 'admin1_code' => '45', 'name' => 'Республика Марий Эл'],
            ['country_code' => 'RU', 'admin1_code' => '46', 'name' => 'Республика Мордовия'],
            ['country_code' => 'RU', 'admin1_code' => '47', 'name' => 'Московская область'],
            ['country_code' => 'RU', 'admin1_code' => '48', 'name' => 'Москва (город)'],
            ['country_code' => 'RU', 'admin1_code' => '49', 'name' => 'Мурманская область'],
            ['country_code' => 'RU', 'admin1_code' => '50', 'name' => 'Ненецкий автономный округ'],
            ['country_code' => 'RU', 'admin1_code' => '51', 'name' => 'Нижегородская область'],
            ['country_code' => 'RU', 'admin1_code' => '52', 'name' => 'Новгородская область'],
            ['country_code' => 'RU', 'admin1_code' => '53', 'name' => 'Новосибирская область'],
            ['country_code' => 'RU', 'admin1_code' => '54', 'name' => 'Омская область'],
            ['country_code' => 'RU', 'admin1_code' => '55', 'name' => 'Оренбургская область'],
            ['country_code' => 'RU', 'admin1_code' => '56', 'name' => 'Орловская область'],
            ['country_code' => 'RU', 'admin1_code' => '57', 'name' => 'Пензенская область'],
            ['country_code' => 'RU', 'admin1_code' => '59', 'name' => 'Приморский край'],
            ['country_code' => 'RU', 'admin1_code' => '60', 'name' => 'Псковская область'],
            ['country_code' => 'RU', 'admin1_code' => '61', 'name' => 'Ростовская область'],
            ['country_code' => 'RU', 'admin1_code' => '62', 'name' => 'Рязанская область'],
            ['country_code' => 'RU', 'admin1_code' => '63', 'name' => 'Республика Саха (Якутия)'],
            ['country_code' => 'RU', 'admin1_code' => '64', 'name' => 'Сахалинская область'],
            ['country_code' => 'RU', 'admin1_code' => '65', 'name' => 'Самарская область'],
            ['country_code' => 'RU', 'admin1_code' => '66', 'name' => 'Санкт‑Петербург (город)'],
            ['country_code' => 'RU', 'admin1_code' => '67', 'name' => 'Саратовская область'],
            ['country_code' => 'RU', 'admin1_code' => '68', 'name' => 'Республика Северная Осетия‑Алания'],
            ['country_code' => 'RU', 'admin1_code' => '69', 'name' => 'Смоленская область'],
            ['country_code' => 'RU', 'admin1_code' => '70', 'name' => 'Ставропольский край'],
            ['country_code' => 'RU', 'admin1_code' => '71', 'name' => 'Свердловская область'],
            ['country_code' => 'RU', 'admin1_code' => '72', 'name' => 'Тамбовская область'],
            ['country_code' => 'RU', 'admin1_code' => '73', 'name' => 'Республика Татарстан'],
            ['country_code' => 'RU', 'admin1_code' => '75', 'name' => 'Томская область'],
            ['country_code' => 'RU', 'admin1_code' => '76', 'name' => 'Тульская область'],
            ['country_code' => 'RU', 'admin1_code' => '77', 'name' => 'Тверская область'],
            ['country_code' => 'RU', 'admin1_code' => '78', 'name' => 'Тюменская область'],
            ['country_code' => 'RU', 'admin1_code' => '79', 'name' => 'Республика Тыва'],
            ['country_code' => 'RU', 'admin1_code' => '80', 'name' => 'Удмуртская Республика'],
            ['country_code' => 'RU', 'admin1_code' => '81', 'name' => 'Ульяновская область'],
            ['country_code' => 'RU', 'admin1_code' => '83', 'name' => 'Владимирская область'],
            ['country_code' => 'RU', 'admin1_code' => '84', 'name' => 'Волгоградская область'],
            ['country_code' => 'RU', 'admin1_code' => '85', 'name' => 'Вологодская область'],
            ['country_code' => 'RU', 'admin1_code' => '86', 'name' => 'Воронежская область'],
            ['country_code' => 'RU', 'admin1_code' => '87', 'name' => 'Ямало‑Ненецкий автономный округ'],
            ['country_code' => 'RU', 'admin1_code' => '88', 'name' => 'Ярославская область'],
            ['country_code' => 'RU', 'admin1_code' => '89', 'name' => 'Еврейская автономная область'],
            ['country_code' => 'RU', 'admin1_code' => '90', 'name' => 'Пермский край'],
            ['country_code' => 'RU', 'admin1_code' => '91', 'name' => 'Красноярский край'],
            ['country_code' => 'RU', 'admin1_code' => '92', 'name' => 'Камчатский край'],
        ];

        DB::table('admin1_codes')->insert($data);
    }
}
