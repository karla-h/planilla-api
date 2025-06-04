<?php
namespace App\Architecture\Domain\Models\Entities;

use Spatie\LaravelData\Data;

class AffiliationData extends Data 
{
    public $id;
    public $description;
    public $percent;
}