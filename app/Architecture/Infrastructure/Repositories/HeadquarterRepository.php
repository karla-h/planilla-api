<?php

namespace App\Architecture\Infrastructure\Repositories;

use App\Architecture\Domain\Models\Entities\HeadquarterData;
use App\Exceptions\EntityNotFoundException;
use App\Models\Headquarter;

class HeadquarterRepository implements IBaseRepository
{
    public function create($data)
    {
        try {
            $headquarter = Headquarter::create($data);
            return [
                'message' => 'Headquarter created successfully',
                'data' => HeadquarterData::from($headquarter),
                'status' => 201
            ];
        } catch (\Throwable $th) {
            return ['message' => 'Error, data cannot be processed: ' . $th->getMessage(), 'status' => 500];
        }
    }

    public function edit($key, $data)
    {
        $headquarter = Headquarter::where('name', $key)->first();

        if (!$headquarter) {
            throw new EntityNotFoundException('Headquarter not found');
        }

        $headquarter->update($data);
        return ['message' => 'Headquarter updated successfully', 'status' => 200];
    }

    public function findBy($key)
    {
        $headquarter = Headquarter::where('name', $key)->first();

        if (!$headquarter) {
            throw new EntityNotFoundException('Headquarter not found');
        }

        return HeadquarterData::optional($headquarter);
    }

    public function findAll()
    {
        return HeadquarterData::collect(Headquarter::all());
    }

    public function delete($key)
    {
        $headquarter = Headquarter::where('name', $key)->first();

        if (!$headquarter) {
            throw new EntityNotFoundException('Headquarter not found');
        }

        $headquarter->delete();
        return ['message' => 'Headquarter deleted successfully', 'status' => 202];
    }
}
