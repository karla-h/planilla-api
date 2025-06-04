<?php

namespace App\Architecture\Infrastructure\Repositories;

use App\Architecture\Domain\Models\Entities\AffiliationData;
use App\Exceptions\EntityNotFoundException;
use App\Models\Affiliation;

class AffiliationRepository implements IBaseRepository
{
    public function create($data)
    {
        try {
            $affiliation = Affiliation::create($data);
            return [
                'message' => 'Affiliation type created successfully',
                'data' => AffiliationData::from($affiliation),
                'status' => 201
            ];
        } catch (\Throwable $th) {
            return ['message' => 'Error, data cannot be processed: ' . $th->getMessage(), 'status' => 500];
        }
    }

    public function edit($key, $data)
    {
        $affiliation = Affiliation::where('description',$key)->first();

        if (!$affiliation) {
            throw new EntityNotFoundException('Affiliation type not found');
        }

        $affiliation->update($data);
        return ['message' => 'Affiliation type updated successfully', 'status' => 200];
    }

    public function findBy($key)
    {
        $affiliation = Affiliation::where('description',$key)->first();;

        if (!$affiliation) {
            throw new EntityNotFoundException('Affiliation type not found');
        }

        return AffiliationData::optional($affiliation);
    }

    public function findAll()
    {
        return AffiliationData::collect(Affiliation::all());
    }

    public function delete($key)
    {
        $affiliation = Affiliation::where('description',$key)->first();

        if (!$affiliation) {
            throw new EntityNotFoundException('Affiliation type not found');
        }

        $affiliation->delete();
        return ['message' => 'Affiliation type deleted successfully', 'status' => 202];
    }
}
