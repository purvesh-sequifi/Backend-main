<?php

namespace Database\Seeders;

use App\Models\Timezone;
use Illuminate\Database\Seeder;

class TimezoneSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    private array $timezones = [
        ['name' => '(UTC-12:00) International Date Line West', 'timezone' => 'Etc/GMT+12'],
        ['name' => '(UTC-11:00) Coordinated Universal Time-11', 'timezone' => 'Etc/GMT+11'],
        ['name' => '(UTC-10:00) Hawaii', 'timezone' => 'Etc/GMT+10'],
        ['name' => '(UTC-09:00) Alaska', 'timezone' => 'America/Anchorage'],
        ['name' => '(UTC-08:00) Baja California', 'timezone' => 'America/Santa_Isabel'],
        ['name' => '(UTC-07:00) Pacific Daylight Time (US & Canada)', 'timezone' => 'America/Los_Angeles'],
        ['name' => '(UTC-08:00) Pacific Standard Time (US & Canada)', 'timezone' => 'America/Los_Angeles'],
        ['name' => '(UTC-07:00) Arizona', 'timezone' => 'America/Creston'],
        ['name' => '(UTC-07:00) Chihuahua, La Paz, Mazatlan', 'timezone' => 'America/Chihuahua'],
        ['name' => '(UTC-07:00) Mountain Time (US & Canada)', 'timezone' => 'America/Boise'],
        ['name' => '(UTC-06:00) Central America', 'timezone' => 'America/Belize'],
        ['name' => '(UTC-06:00) Central Time (US & Canada)', 'timezone' => 'America/Chicago'],
        ['name' => '(UTC-06:00) Guadalajara, Mexico City, Monterrey', 'timezone' => 'America/Bahia_Banderas'],
        ['name' => '(UTC-06:00) Saskatchewan', 'timezone' => 'America/Regina'],
        ['name' => '(UTC-05:00) Bogota, Lima, Quito', 'timezone' => 'America/Bogota'],
        ['name' => '(UTC-05:00) Eastern Time (US & Canada)', 'timezone' => 'America/Detroit'],
        ['name' => '(UTC-04:00) Eastern Daylight Time (US & Canada)', 'timezone' => 'America/Detroit'],
        ['name' => '(UTC-05:00) Indiana (East)', 'timezone' => 'America/Indiana/Marengo'],
        ['name' => '(UTC-04:30) Caracas', 'timezone' => 'America/Caracas'],
        ['name' => '(UTC-04:00) Asuncion', 'timezone' => 'America/Asuncion'],
        ['name' => '(UTC-04:00) Atlantic Time (Canada)', 'timezone' => 'America/Glace_Bay'],
        ['name' => '(UTC-04:00) Cuiaba', 'timezone' => 'America/Campo_Grande'],
        ['name' => '(UTC-04:00) Georgetown, La Paz, Manaus, San Juan', 'timezone' => 'America/Anguilla'],
        ['name' => '(UTC-04:00) Santiago', 'timezone' => 'America/Santiago'],
        ['name' => '(UTC-03:30) Newfoundland', 'timezone' => 'America/St_Johns'],
        ['name' => '(UTC-03:00) Brasilia', 'timezone' => 'America/Sao_Paulo'],
        ['name' => '(UTC-03:00) Buenos Aires', 'timezone' => 'America/Argentina/Buenos_Aires'],
        ['name' => '(UTC-03:00) Cayenne, Fortaleza', 'timezone' => 'America/Araguaina'],
        ['name' => '(UTC-03:00) Greenland', 'timezone' => 'America/Godthab'],
        ['name' => '(UTC-03:00) Montevideo', 'timezone' => 'America/Montevideo'],
        ['name' => '(UTC-03:00) Salvador', 'timezone' => 'America/Bahia'],
        ['name' => '(UTC-02:00) Coordinated Universal Time-02', 'timezone' => 'America/Noronha'],
        ['name' => '(UTC-01:00) Azores', 'timezone' => 'America/Scoresbysund'],
        ['name' => '(UTC-01:00) Cape Verde Is.', 'timezone' => 'Atlantic/Cape_Verde'],
        ['name' => '(UTC) Casablanca', 'timezone' => 'Africa/Casablanca'],
        ['name' => '(UTC) Coordinated Universal Time', 'timezone' => 'America/Danmarkshavn'],
        ['name' => '(UTC) Edinburgh, London', 'timezone' => 'Europe/Isle_of_Man'],
        ['name' => '(UTC+01:00) Edinburgh, London', 'timezone' => 'Europe/Isle_of_Man'],
        ['name' => '(UTC) Dublin, Lisbon', 'timezone' => 'Atlantic/Canary'],
        ['name' => '(UTC) Monrovia, Reykjavik', 'timezone' => 'Africa/Abidjan'],
        ['name' => '(UTC+01:00) Amsterdam, Berlin, Bern, Rome, Stockholm, Vienna', 'timezone' => 'Arctic/Longyearbyen'],
        ['name' => '(UTC+01:00) Belgrade, Bratislava, Budapest, Ljubljana, Prague', 'timezone' => 'Europe/Belgrade'],
        ['name' => '(UTC+01:00) Brussels, Copenhagen, Madrid, Paris', 'timezone' => 'Africa/Ceuta'],
        ['name' => '(UTC+01:00) Sarajevo, Skopje, Warsaw, Zagreb', 'timezone' => 'Europe/Sarajevo'],
        ['name' => '(UTC+01:00) West Central Africa', 'timezone' => 'Africa/Algiers'],
        ['name' => '(UTC+01:00) Windhoek', 'timezone' => 'Africa/Windhoek'],
        ['name' => '(UTC+02:00) Athens, Bucharest', 'timezone' => 'Asia/Nicosia'],
        ['name' => '(UTC+02:00) Beirut', 'timezone' => 'Asia/Beirut'],
        ['name' => '(UTC+02:00) Cairo', 'timezone' => 'Africa/Cairo'],
        ['name' => '(UTC+02:00) Damascus', 'timezone' => 'Asia/Damascus'],
        ['name' => '(UTC+02:00) E. Europe', 'timezone' => 'Asia/Nicosia'],
        ['name' => '(UTC+02:00) Harare, Pretoria', 'timezone' => 'Africa/Blantyre'],
        ['name' => '(UTC+02:00) Helsinki, Kyiv, Riga, Sofia, Tallinn, Vilnius', 'timezone' => 'Europe/Helsinki'],
        ['name' => '(UTC+03:00) Istanbul', 'timezone' => 'Europe/Istanbul'],
        ['name' => '(UTC+02:00) Jerusalem', 'timezone' => 'Asia/Jerusalem'],
        ['name' => '(UTC+02:00) Tripoli', 'timezone' => 'Africa/Tripoli'],
        ['name' => '(UTC+03:00) Amman', 'timezone' => 'Asia/Amman'],
        ['name' => '(UTC+03:00) Baghdad', 'timezone' => 'Asia/Baghdad'],
        ['name' => '(UTC+02:00) Kaliningrad', 'timezone' => 'Europe/Kaliningrad'],
        ['name' => '(UTC+03:00) Kuwait, Riyadh', 'timezone' => 'Asia/Aden'],
        ['name' => '(UTC+03:00) Nairobi', 'timezone' => 'Africa/Addis_Ababa'],
        ['name' => '(UTC+03:00) Moscow, St. Petersburg, Volgograd, Minsk', 'timezone' => 'Europe/Kirov'],
        ['name' => '(UTC+04:00) Samara, Ulyanovsk, Saratov', 'timezone' => 'Europe/Astrakhan'],
        ['name' => '(UTC+03:30) Tehran', 'timezone' => 'Asia/Tehran'],
        ['name' => '(UTC+04:00) Abu Dhabi, Muscat', 'timezone' => 'Asia/Dubai'],
        ['name' => '(UTC+04:00) Baku', 'timezone' => 'Asia/Baku'],
        ['name' => '(UTC+04:00) Port Louis', 'timezone' => 'Indian/Mahe'],
        ['name' => '(UTC+04:00) Tbilisi', 'timezone' => 'Asia/Tbilisi'],
        ['name' => '(UTC+04:00) Yerevan', 'timezone' => 'Asia/Yerevan'],
        ['name' => '(UTC+04:30) Kabul', 'timezone' => 'Asia/Kabul'],
        ['name' => '(UTC+05:00) Ashgabat, Tashkent', 'timezone' => 'Antarctica/Mawson'],
        ['name' => '(UTC+05:00) Yekaterinburg', 'timezone' => 'Asia/Yekaterinburg'],
        ['name' => '(UTC+05:00) Islamabad, Karachi', 'timezone' => 'Asia/Karachi'],
        ['name' => '(UTC+05:30) Chennai, Kolkata, Mumbai, New Delhi', 'timezone' => 'Asia/Kolkata'],
        ['name' => '(UTC+05:30) Sri Jayawardenepura', 'timezone' => 'Asia/Colombo'],
        ['name' => '(UTC+05:45) Kathmandu', 'timezone' => 'Asia/Kathmandu'],
        ['name' => '(UTC+06:00) Nur-Sultan (Astana)', 'timezone' => 'Antarctica/Vostok'],
        ['name' => '(UTC+06:00) Dhaka', 'timezone' => 'Asia/Dhaka'],
        ['name' => '(UTC+06:30) Yangon (Rangoon)', 'timezone' => 'Asia/Rangoon'],
        ['name' => '(UTC+07:00) Bangkok, Hanoi, Jakarta', 'timezone' => 'Antarctica/Davis'],
        ['name' => '(UTC+07:00) Novosibirsk', 'timezone' => 'Asia/Novokuznetsk'],
        ['name' => '(UTC+08:00) Beijing, Chongqing, Hong Kong, Urumqi', 'timezone' => 'Asia/Hong_Kong'],
        ['name' => '(UTC+08:00) Krasnoyarsk', 'timezone' => 'Asia/Krasnoyarsk'],
        ['name' => '(UTC+08:00) Kuala Lumpur, Singapore', 'timezone' => 'Asia/Brunei'],
        ['name' => '(UTC+08:00) Perth', 'timezone' => 'Antarctica/Casey'],
        ['name' => '(UTC+08:00) Taipei', 'timezone' => 'Asia/Taipei'],
        ['name' => '(UTC+08:00) Ulaanbaatar', 'timezone' => 'Asia/Choibalsan'],
        ['name' => '(UTC+08:00) Irkutsk', 'timezone' => 'Asia/Irkutsk'],
        ['name' => '(UTC+09:00) Osaka, Sapporo, Tokyo', 'timezone' => 'Asia/Dili'],
        ['name' => '(UTC+09:00) Seoul', 'timezone' => 'Asia/Pyongyang'],
        ['name' => '(UTC+09:30) Adelaide', 'timezone' => 'Australia/Adelaide'],
        ['name' => '(UTC+09:30) Darwin', 'timezone' => 'Australia/Darwin'],
        ['name' => '(UTC+10:00) Brisbane', 'timezone' => 'Australia/Brisbane'],
        ['name' => '(UTC+10:00) Canberra, Melbourne, Sydney', 'timezone' => 'Australia/Melbourne'],
        ['name' => '(UTC+10:00) Guam, Port Moresby', 'timezone' => 'Antarctica/DumontDUrville'],
        ['name' => '(UTC+10:00) Hobart', 'timezone' => 'Australia/Currie'],
        ['name' => '(UTC+09:00) Yakutsk', 'timezone' => 'Asia/Chita'],
        ['name' => '(UTC+11:00) Solomon Is., New Caledonia', 'timezone' => 'Antarctica/Macquarie'],
        ['name' => '(UTC+11:00) Vladivostok', 'timezone' => 'Asia/Sakhalin'],
        ['name' => '(UTC+12:00) Auckland, Wellington', 'timezone' => 'Antarctica/McMurdo'],
        ['name' => '(UTC+12:00) Coordinated Universal Time+12', 'timezone' => 'Etc/GMT-12'],
        ['name' => '(UTC+12:00) Fiji', 'timezone' => 'Pacific/Fiji'],
        ['name' => '(UTC+12:00) Magadan', 'timezone' => 'Asia/Anadyr'],
        ['name' => '(UTC+12:00) Petropavlovsk-Kamchatsky - Old', 'timezone' => 'Asia/Kamchatka'],
        ['name' => "(UTC+13:00) Nuku'alofa", 'timezone' => 'Etc/GMT-13'],
        ['name' => '(UTC+13:00) Samoa', 'timezone' => 'Pacific/Apia'],
    ];

    public function run(): void
    {
        if ($this->timezones && count($this->timezones) != 0) {
            foreach ($this->timezones as $timeZone) {
                Timezone::updateOrCreate(['name' => $timeZone['name'], 'timezone' => $timeZone['timezone']], $timeZone);
            }
        }
    }
}
