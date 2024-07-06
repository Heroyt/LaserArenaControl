<?php

namespace App\Controllers\Api;

use App\Api\Response\ErrorDto;
use App\Api\Response\ErrorType;
use App\Models\PriceGroup;
use JsonException;
use Lsr\Core\Controllers\ApiController;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Core\Requests\Request;
use Psr\Http\Message\ResponseInterface;

class PriceGroups extends ApiController
{
    public function list(): ResponseInterface {
        return $this->respond(array_values(PriceGroup::getAll()));
    }

    public function show(PriceGroup $priceGroup): ResponseInterface {
        return $this->respond($priceGroup);
    }

    public function create(Request $request): ResponseInterface {
        /** @var string|null $name */
        $name = $request->getPost('name');
        if (empty($name)) {
            return $this->respond(new ErrorDto('`Name` is required', ErrorType::VALIDATION), 400);
        }
        if (!is_string($name)) {
            return $this->respond(new ErrorDto('`Name` must be a string', ErrorType::VALIDATION), 400);
        }

        $price = $request->getPost('price');
        if (empty($price)) {
            return $this->respond(new ErrorDto('`Price` is required', ErrorType::VALIDATION), 400);
        }
        if (!is_numeric($price)) {
            return $this->respond(new ErrorDto('`Price` must be a number', ErrorType::VALIDATION), 400);
        }

        $priceGroup = new PriceGroup();
        $priceGroup->name = $name;
        $priceGroup->setPrice((float) $price);

        try {
            if (!$priceGroup->save()) {
                return $this->respond(new ErrorDto('Error while saving the price group.'), 500);
            }
        } catch (JsonException | ValidationException $e) {
            return $this->respond(new ErrorDto('Error', detail: $e->getMessage(), exception: $e), 500);
        }
        return $this->respond($priceGroup, 201);
    }

    public function update(PriceGroup $priceGroup, Request $request): ResponseInterface {
        /** @var string|null $name */
        $name = $request->getPost('name', $priceGroup->name);
        if (empty($name)) {
            return $this->respond(new ErrorDto('`Name` is required', ErrorType::VALIDATION), 400);
        }
        if (!is_string($name)) {
            return $this->respond(new ErrorDto('`Name` must be a string', ErrorType::VALIDATION), 400);
        }

        $price = $request->getPost('price', $priceGroup->getPrice());
        if (empty($price)) {
            return $this->respond(new ErrorDto('`Price` is required', ErrorType::VALIDATION), 400);
        }
        if (!is_numeric($price)) {
            return $this->respond(new ErrorDto('`Price` must be a number', ErrorType::VALIDATION), 400);
        }

        $priceGroup->name = $name;
        $priceGroup->setPrice((float) $price);

        try {
            if (!$priceGroup->save()) {
                return $this->respond(new ErrorDto('Error while saving the price group.'), 500);
            }
        } catch (JsonException | ValidationException $e) {
            return $this->respond(new ErrorDto('Error', detail: $e->getMessage(), exception: $e), 500);
        }
        return $this->respond($priceGroup, 200);
    }

    public function delete(PriceGroup $priceGroup): ResponseInterface {
        // Soft-delete the entity
        $priceGroup->deleted = true;
        try {
            if (!$priceGroup->save()) {
                return $this->respond(new ErrorDto('Error while deleting the price group.'), 500);
            }
        } catch (JsonException | ValidationException $e) {
            return $this->respond(new ErrorDto('Error', detail: $e->getMessage(), exception: $e), 500);
        }
        return $this->respond('ok');
    }
}
