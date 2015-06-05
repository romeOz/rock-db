<?php
namespace rockunit\models;


class CustomerRules extends Customer
{
    public function rules()
    {
        return [
            [
                'name', 'required', 'int'
            ],
        ];
    }
}
