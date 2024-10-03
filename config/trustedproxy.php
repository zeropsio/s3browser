<?php

return [

     'proxies' => '*',

    /*
     * Which headers to use to detect proxy related data (For, Host, Proto, Port)
     *
     * Options include:
     *
     * - All headers (see below) - Trust all x-forwarded-* headers
     * - Illuminate\Http\Request::HEADER_FORWARDED - Use the FORWARDED header to establish trust
     * - Illuminate\Http\Request::HEADER_X_FORWARDED_AWS_ELB - If you are using AWS Elastic Load Balancer
     *
     * @link https://symfony.com/doc/current/deployment/proxies.html
     */
    'headers' => Illuminate\Http\Request::HEADER_X_FORWARDED_FOR | Illuminate\Http\Request::HEADER_X_FORWARDED_HOST | Illuminate\Http\Request::HEADER_X_FORWARDED_PORT | Illuminate\Http\Request::HEADER_X_FORWARDED_PROTO | Illuminate\Http\Request::HEADER_X_FORWARDED_AWS_ELB,

];
