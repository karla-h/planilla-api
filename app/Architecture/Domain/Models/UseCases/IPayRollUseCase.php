<?php

namespace App\Architecture\Domain\Models\UseCases;

interface IPayRollUseCase extends IBaseUseCase
{

    public function findByEmployeeAndPaydate($employee, $pay_date);


}