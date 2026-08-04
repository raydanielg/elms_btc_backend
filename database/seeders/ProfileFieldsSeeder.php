<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\MUserInfoCategory;
use App\Models\MUserInfoField;

class ProfileFieldsSeeder extends Seeder
{
    public function run(): void
    {
        // Category: Academic Info
        $academicCat = MUserInfoCategory::firstOrCreate(
            ['name' => 'Academic Information'],
            ['sortorder' => 1]
        );

        MUserInfoField::firstOrCreate(
            ['shortname' => 'necta_index_no'],
            [
                'category_id' => $academicCat->id,
                'name' => 'NECTA Index Number',
                'datatype' => 'text',
                'sortorder' => 1,
                'required' => false,
                'signup' => true,
                'visible' => true,
            ]
        );

        MUserInfoField::firstOrCreate(
            ['shortname' => 'previous_school'],
            [
                'category_id' => $academicCat->id,
                'name' => 'Previous School',
                'datatype' => 'text',
                'sortorder' => 2,
                'required' => false,
                'signup' => true,
                'visible' => true,
            ]
        );

        // Category: Personal Info
        $personalCat = MUserInfoCategory::firstOrCreate(
            ['name' => 'Personal Information'],
            ['sortorder' => 2]
        );

        MUserInfoField::firstOrCreate(
            ['shortname' => 'region'],
            [
                'category_id' => $personalCat->id,
                'name' => 'Region',
                'datatype' => 'menu',
                'param1' => 'Dar es Salaam, Dodoma, Arusha, Mwanza, Mbeya, Tanga, Morogoro, Kilimanjaro, Mtwara, Lindi, Ruvuma, Iringa, Singida, Tabora, Kigoma, Shinyanga, Kagera, Geita, Simiyu, Songwe, Rukwa, Katavi, Manyara, Njombe, Pwani, Zanzibar North, Zanzibar South, Zanzibar Urban, Pemba North, Pemba South',
                'sortorder' => 1,
                'required' => false,
                'signup' => true,
                'visible' => true,
            ]
        );

        MUserInfoField::firstOrCreate(
            ['shortname' => 'mode'],
            [
                'category_id' => $personalCat->id,
                'name' => 'Study Mode',
                'datatype' => 'menu',
                'param1' => 'Masafa,Distant,Kutwa,Bweni',
                'sortorder' => 2,
                'required' => false,
                'signup' => true,
                'visible' => true,
            ]
        );

        MUserInfoField::firstOrCreate(
            ['shortname' => 'gender'],
            [
                'category_id' => $personalCat->id,
                'name' => 'Gender',
                'datatype' => 'menu',
                'param1' => 'Male,Female',
                'sortorder' => 3,
                'required' => false,
                'signup' => true,
                'visible' => true,
            ]
        );

        MUserInfoField::firstOrCreate(
            ['shortname' => 'date_of_birth',
            ],
            [
                'category_id' => $personalCat->id,
                'name' => 'Date of Birth',
                'datatype' => 'date',
                'sortorder' => 4,
                'required' => false,
                'signup' => true,
                'visible' => true,
            ]
        );
    }
}
