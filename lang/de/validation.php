<?php

/*
 * German validation messages for the rules this app uses. Rules not listed here fall back to English.
 */

return [
    'after' => ':attribute muss ein Datum nach :date sein.',
    'after_or_equal' => ':attribute muss ein Datum am oder nach :date sein.',
    'before' => ':attribute muss ein Datum vor :date sein.',
    'before_or_equal' => ':attribute muss ein Datum am oder vor :date sein.',
    'date' => ':attribute muss ein gültiges Datum sein.',
    'email' => ':attribute muss eine gültige E-Mail-Adresse sein.',
    'enum' => 'Der gewählte Wert für :attribute ist ungültig.',
    'in' => 'Der gewählte Wert für :attribute ist ungültig.',
    'integer' => ':attribute muss eine ganze Zahl sein.',
    'max' => [
        'numeric' => ':attribute darf nicht größer als :max sein.',
        'string' => ':attribute darf nicht länger als :max Zeichen sein.',
    ],
    'min' => [
        'numeric' => ':attribute muss mindestens :min sein.',
        'string' => ':attribute muss mindestens :min Zeichen lang sein.',
    ],
    'numeric' => ':attribute muss eine Zahl sein.',
    'required' => ':attribute ist ein Pflichtfeld.',
    'required_with' => ':attribute muss ausgefüllt werden, wenn :values angegeben ist.',
    'string' => ':attribute muss ein Text sein.',
    'unique' => ':attribute ist bereits vergeben.',

    'attributes' => [],
];
