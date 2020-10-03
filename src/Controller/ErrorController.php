<?php

namespace App\Controller;

use App\Base\RenoController;
use Symfony\Component\HttpFoundation\Request;

class ErrorController extends RenoController
{

    public function show(Request $request, $exception)
    {
        $this->title = 'Error Found';
        if ($exception->getCode() == 403) {
            $this->title = 'Access Denied';
        }
        return $this->errorPage($this->title, $exception->getMessage());
    }
}