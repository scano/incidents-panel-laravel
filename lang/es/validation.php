<?php

return [
    'required' => 'El campo :attribute es obligatorio.',
    'email' => 'Introduce una dirección de correo válida.',
    'string' => 'El campo :attribute debe ser un texto.',
    'enum' => 'Selecciona un valor válido para :attribute.',
    'max' => ['string' => 'El campo :attribute no puede superar :max caracteres.'],
    'attributes' => [
        'title' => 'título', 'description' => 'descripción', 'priority' => 'prioridad',
        'requester_name' => 'solicitante', 'assigned_to' => 'responsable', 'user_name' => 'nombre del actor',
        'reason' => 'motivo', 'author_name' => 'autor', 'body' => 'comentario', 'target' => 'nuevo estado', 'email' => 'correo electrónico', 'password' => 'contraseña',
    ],
];
