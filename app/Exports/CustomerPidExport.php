<?php

namespace App\Exports;

use App\Models\SalesMaster;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class CustomerPidExport implements FromCollection, WithHeadings
{
    public function __construct($startDate = 0, $endDate = 0) {}

    public function collection(): Collection
    {

        $records = ['Chris Sutherland',
            'Andrew Jenkins',
            'Alene Dowling',
            'Gayla Goodwin',
            'Juan Sanchez',
            'Markley Owings',
            'James Ballard',
            'Eric Selander',
            'Brent gravois',
            'Jason Bechel',
            'Nancy Sanchez',
            'David Henson',
            'Rachel Stefancik',
            'Kevin Beeker',
            'Kendra Reitz',
            'Phil Forbeck',
            'Angelica Bechtold',
            'Susan Likes',
            'Charles SmithDonald Howe',
            'Thomas skrip',
            'Leigh Ann Cruse',
            'Trisha Martin',
            'Tiffany Boyd',
            'Harvey Beaver',
            'James Kelly',
            'Cody Jakobitz',
            'Dale BrandlTim Heaton',
            'Nick Frerichs',
            'Anthony Isom',
            'George Kirgan',
            'Bob Lehrman',
            'Eric crownhart',
            'David Ziegler',
            'Grady Manning',
            'Jacquelyn Gonski',
            'Chris Thebeau',
            'Gail Shelby',
            'Daniel Lee',
            'Jordan Panger',
            'Bob Thebeau',
            'Joseph hanfelder',
            'James heron',
            'Lonnie Teague',
            'Kimberly tingePreston Vanderven',
            'Nick Clark',
            'Joe Kintner',
            'David Vieregge',
            'Taff harris',
            'Calvin Gray',
            'Andrea Newman',
            'Haylee Bruce',
            'Thomas Nix',
            'Linda Sutrina',
            'Ismael Cruz',
            'Andrea Beck',
            'Alan Griffin',
            'Sydney Qualls',
            'Ben Fay',
            'Penny WileyDavid Sauerwein',
            'Robert Soncasie',
            'Brent Goetz',
            'Rich Sturgill',
            'Susan Likes',
            'Donald Howe',
            'Cody Jakobitz',
            'Danielle Moser',
            'Jacob Hileman',
            'Jeremy Hillyer',
            'David Sloan',
            'Deborah Wheeler',
            'Jose Arreguin',
            'Victor Hernandez',
            'Steven Hotchkiss',
            'Chris Johnson',
            'Cheryl Williams',
            'Leah Turnmire',
            'Carmen Lopez',
            'Abbi Devore',
            'Nancy Wolber',
            'Gregory Clark',
            'Sui Tiala',
            'Ronald Clark',
            'Joseph Robinson',
            'Reed Wisley',
            'Annabelle Smith',
            'Ragina smith',
            'Theresa Carbo',
            'Wendy Fellenzer',
            'Todd Rasche',
            'Morgan Zimmer',
            'Tuan Ceu',
        ];
        $result = [];
        foreach ($records as $record) {

            $pid = SalesMaster::where('customer_name', $record)->pluck('pid')->toArray();
            $pids = implode(',', $pid);
            $result[] = [
                'customer_name' => $record,
                'pid' => $pids,

            ];
        }

        return collect($result);
    }

    public function headings(): array
    {
        return [
            'Customer Name',
            'PID',
        ];
    }
}
