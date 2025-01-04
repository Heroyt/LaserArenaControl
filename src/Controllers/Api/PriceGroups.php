<?php

namespace App\Controllers\Api;

use App\Models\PriceGroup;
use Lsr\Core\Controllers\ApiController;
use Lsr\Core\Requests\Dto\ErrorResponse;
use Lsr\Core\Requests\Dto\SuccessResponse;
use Lsr\Core\Requests\Enums\ErrorType;
use Lsr\Core\Requests\Request;
use Lsr\ObjectValidation\Exceptions\ValidationException;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;

/**
 *
 */
class PriceGroups extends ApiController
{
    #[OA\Get(
      path       : '/api/pricegroups',
      operationId: 'listPriceGroups',
      description: 'Get a list of all price groups',
      tags       : ['Price groups'],
    )]
    #[OA\Response(
      response   : 200,
      description: 'PriceGroup list',
      content    : new OA\JsonContent(
        type : 'array',
        items: new OA\Items(ref: '#/components/schemas/PriceGroup')
      )
    )]
    public function list() : ResponseInterface {
        return $this->respond(array_values(PriceGroup::getAll()));
    }

    #[OA\Get(
      path       : '/api/pricegroups/{id}',
      operationId: 'showPriceGroups',
      description: 'Get a price group',
      tags       : ['Price groups'],
    )]
    #[OA\Parameter(name: 'id', description: 'The price group ID', in: 'path', required: true)]
    #[OA\Response(
      response   : 200,
      description: 'PriceGroup',
      content    : new OA\JsonContent(
        ref: '#/components/schemas/PriceGroup'
      )
    )]
    #[OA\Response(
      response   : 404,
      description: 'PriceGroup not found',
      content    : new OA\JsonContent(
        ref: '#/components/schemas/ErrorResponse'
      )
    )]
    public function show(PriceGroup $priceGroup) : ResponseInterface {
        return $this->respond($priceGroup);
    }

    #[OA\Post(
      path       : '/api/pricegroups',
      operationId: 'createPriceGroup',
      description: 'Create a new price group',
      requestBody: new OA\RequestBody(
        required: true,
        content : new OA\JsonContent(
                    required  : ["name", 'price'],
                    properties: [
                                  new OA\Property(
                                    property   : "name",
                                    description: 'Price group name',
                                    type       : "string",
                                    example    : 'Standard'
                                  ),
                                  new OA\Property(
                                    property   : "price",
                                    description: 'Price value.',
                                    type       : "float",
                                    example    : '123.45'
                                  ),
                                ],
                    type      : 'object',
                  ),
      ),
      tags       : ['Price groups'],
    )]
    #[OA\Response(
      response   : 201,
      description: 'PriceGroup created',
      content    : new OA\JsonContent(
        ref: '#/components/schemas/PriceGroup',
      )
    )]
    #[OA\Response(
      response   : 400,
      description: 'Request error',
      content    : new OA\JsonContent(
        ref: '#/components/schemas/ErrorResponse',
      )
    )]
    #[OA\Response(
      response   : 500,
      description: 'Internal error',
      content    : new OA\JsonContent(
        ref: '#/components/schemas/ErrorResponse',
      )
    )]
    public function create(Request $request) : ResponseInterface {
        $name = $request->getPost('name');
        if (empty($name)) {
            return $this->respond(new ErrorResponse('`Name` is required', ErrorType::VALIDATION), 400);
        }
        if (!is_string($name)) {
            return $this->respond(new ErrorResponse('`Name` must be a string', ErrorType::VALIDATION), 400);
        }

        $price = $request->getPost('price');
        if (empty($price)) {
            return $this->respond(new ErrorResponse('`Price` is required', ErrorType::VALIDATION), 400);
        }
        if (!is_numeric($price)) {
            return $this->respond(new ErrorResponse('`Price` must be a number', ErrorType::VALIDATION), 400);
        }

        $priceGroup = new PriceGroup();
        $priceGroup->name = $name;
        $priceGroup->setPrice((float) $price);

        try {
            if (!$priceGroup->save()) {
                return $this->respond(new ErrorResponse('Error while saving the price group.'), 500);
            }
        } catch (ValidationException $e) {
            return $this->respond(new ErrorResponse('Error', detail: $e->getMessage(), exception: $e), 500);
        }
        return $this->respond($priceGroup, 201);
    }

    #[OA\Post(
      path       : '/api/pricegroups/{id}',
      operationId: 'updatePriceGroup',
      description: 'Update a price group',
      requestBody: new OA\RequestBody(
        required: true,
        content : new OA\JsonContent(
                    required  : ["name", 'price'],
                    properties: [
                                  new OA\Property(
                                    property   : "name",
                                    description: 'Price group name',
                                    type       : "string",
                                    example    : 'Standard'
                                  ),
                                  new OA\Property(
                                    property   : "price",
                                    description: 'Price value.',
                                    type       : "float",
                                    example    : '123.45'
                                  ),
                                ],
                    type      : 'object',
                  ),
      ),
      tags       : ['Price groups'],
    )]
    #[OA\Parameter(name: 'id', description: 'The price group ID', in: 'path', required: true)]
    #[OA\Response(
      response   : 200,
      description: 'PriceGroup updated',
      content    : new OA\JsonContent(
        ref: '#/components/schemas/PriceGroup',
      )
    )]
    #[OA\Response(
      response   : 400,
      description: 'Request error',
      content    : new OA\JsonContent(
        ref: '#/components/schemas/ErrorResponse',
      )
    )]
    #[OA\Response(
      response   : 404,
      description: 'Price group not found',
      content    : new OA\JsonContent(
        ref: '#/components/schemas/ErrorResponse',
      )
    )]
    #[OA\Response(
      response   : 500,
      description: 'Internal error',
      content    : new OA\JsonContent(
        ref: '#/components/schemas/ErrorResponse',
      )
    )]
    public function update(PriceGroup $priceGroup, Request $request) : ResponseInterface {
        $name = $request->getPost('name', $priceGroup->name);
        if (empty($name)) {
            return $this->respond(new ErrorResponse('`Name` is required', ErrorType::VALIDATION), 400);
        }
        if (!is_string($name)) {
            return $this->respond(new ErrorResponse('`Name` must be a string', ErrorType::VALIDATION), 400);
        }

        $price = $request->getPost('price', $priceGroup->getPrice());
        if (empty($price)) {
            return $this->respond(new ErrorResponse('`Price` is required', ErrorType::VALIDATION), 400);
        }
        if (!is_numeric($price)) {
            return $this->respond(new ErrorResponse('`Price` must be a number', ErrorType::VALIDATION), 400);
        }

        $priceGroup->name = $name;
        $priceGroup->setPrice((float) $price);

        try {
            if (!$priceGroup->save()) {
                return $this->respond(new ErrorResponse('Error while saving the price group.'), 500);
            }
        } catch (ValidationException $e) {
            return $this->respond(new ErrorResponse('Error', detail: $e->getMessage(), exception: $e), 500);
        }
        return $this->respond($priceGroup);
    }

    #[OA\Delete(
      path       : '/api/pricegroups/{id}',
      operationId: 'deletePriceGroup',
      description: 'Delete a price group',
      tags       : ['Price groups'],
    )]
    #[OA\Parameter(name: 'id', description: 'The price group ID', in: 'path', required: true)]
    #[OA\Response(
      response   : 200,
      description: 'PriceGroup deleted',
      content    : new OA\JsonContent(
        ref: '#/components/schemas/SuccessResponse',
      )
    )]
    #[OA\Response(
      response   : 404,
      description: 'Price group not found',
      content    : new OA\JsonContent(
        ref: '#/components/schemas/ErrorResponse',
      )
    )]
    #[OA\Response(
      response   : 500,
      description: 'Internal error',
      content    : new OA\JsonContent(
        ref: '#/components/schemas/ErrorResponse',
      )
    )]
    public function delete(PriceGroup $priceGroup) : ResponseInterface {
        // Soft-delete the entity
        $priceGroup->deleted = true;
        try {
            if (!$priceGroup->save()) {
                return $this->respond(new ErrorResponse('Error while deleting the price group.'), 500);
            }
        } catch (ValidationException $e) {
            return $this->respond(new ErrorResponse('Error', detail: $e->getMessage(), exception: $e), 500);
        }
        return $this->respond(new SuccessResponse('Price group deleted.'));
    }
}
