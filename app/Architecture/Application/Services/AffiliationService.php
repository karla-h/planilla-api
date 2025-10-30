<?php

namespace App\Architecture\Application\Services;

use App\Models\Affiliation;
use App\Exceptions\EntityNotFoundException;
use Illuminate\Support\Facades\Log;

class AffiliationService
{
    public function findAll()
    {
        try {
            return Affiliation::all();
        } catch (\Exception $e) {
            Log::error('Error en AffiliationService@findAll: ' . $e->getMessage());
            throw $e;
        }
    }

    public function create(array $data)
    {
        try {
            Log::info('Creando afiliación con datos:', $data);

            $affiliation = Affiliation::create($data);
            
            Log::info('Afiliación creada exitosamente ID: ' . $affiliation->id);

            return [
                'status' => 201,
                'message' => 'Afiliación creada exitosamente',
                'data' => $affiliation
            ];

        } catch (\Exception $e) {
            Log::error('Error en AffiliationService@create: ' . $e->getMessage());
            return [
                'status' => 500,
                'message' => 'Error al crear afiliación: ' . $e->getMessage()
            ];
        }
    }

    public function findBy($id)
    {
        try {
            Log::info('Buscando afiliación ID: ' . $id);
            $affiliation = Affiliation::find($id);
            
            if (!$affiliation) {
                throw new EntityNotFoundException('Afiliación no encontrada');
            }
            
            return $affiliation;
        } catch (EntityNotFoundException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Error en AffiliationService@findBy: ' . $e->getMessage());
            throw $e;
        }
    }

    public function edit($id, array $data)
    {
        try {
            Log::info('Actualizando afiliación ID: ' . $id, $data);

            $affiliation = $this->findBy($id);
            $affiliation->update($data);
            
            Log::info('Afiliación actualizada exitosamente ID: ' . $id);

            return [
                'status' => 200,
                'message' => 'Afiliación actualizada exitosamente'
            ];

        } catch (EntityNotFoundException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Error en AffiliationService@edit: ' . $e->getMessage());
            return [
                'status' => 500,
                'message' => 'Error al actualizar afiliación: ' . $e->getMessage()
            ];
        }
    }

    public function delete($id)
    {
        try {
            Log::info('Eliminando afiliación ID: ' . $id);

            $affiliation = $this->findBy($id);
            $affiliation->delete();
            
            Log::info('Afiliación eliminada exitosamente ID: ' . $id);

            return [
                'status' => 200,
                'message' => 'Afiliación eliminada exitosamente'
            ];

        } catch (EntityNotFoundException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Error en AffiliationService@delete: ' . $e->getMessage());
            return [
                'status' => 500,
                'message' => 'Error al eliminar afiliación: ' . $e->getMessage()
            ];
        }
    }
}