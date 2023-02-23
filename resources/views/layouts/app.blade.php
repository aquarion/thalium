@inject('agent', 'Phattarachai\LaravelMobileDetect\Agent')

<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="">
    <meta name="author" content="Mark Otto, Jacob Thornton, and Bootstrap contributors">
    <meta name="generator" content="Jekyll v4.0.1">

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }} - @yield('title') </title>

    <link rel="canonical" href="{{ url()->current() }}">

    <link rel="icon" type="image/png" sizes="32x32" href="/static/icons/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/static/icons/favicon-16x16.png">
    <link rel="manifest" href="/static/icons/site.webmanifest">
    <link rel="mask-icon" href="/static/icons/safari-pinned-tab.svg" color="#a400ff">
    <link rel="shortcut icon" href="/static/icons/favicon.ico">
    <meta name="msapplication-TileColor" content="#9f00a7">
    <meta name="msapplication-config" content="/static/icons/browserconfig.xml">
    <meta name="theme-color" content="#ffffff">


<meta name="apple-mobile-web-app-title" content="Thalium">
<meta name="application-name" content="Thalium">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black" />
<link rel="apple-touch-startup-image" href="/static/icons/apple-touch-icon.png">
<meta name="mobile-web-app-capable" content="yes">
<link rel="apple-touch-icon" sizes="180x180" href="/static/icons/apple-touch-icon.png">

    <!-- Bootstrap core CSS -->
<link rel="stylesheet" href="/css/app.css">

  </head>
  <body>
    <nav class="navbar navbar-expand-md navbar-dark bg-dark fixed-top">
  <a class="navbar-brand" href="/"><img src="/static/thalium_white.png" >Thalium</a>
  <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarsExampleDefault" aria-controls="navbarsExampleDefault" aria-expanded="false" aria-label="Toggle navigation">
    <span class="navbar-toggler-icon"></span>
  </button>

  <style>
    .nav-link svg {
      color: white;
    }
  </style>

  <div class="collapse navbar-collapse" id="navbarsExampleDefault">
    <ul class="navbar-nav mr-auto d-flex">
      <li class="nav-item active">
        <a class="nav-link" href="/">Home <span class="visually-hidden">(current)</span></a>
      </li>
      <li class="nav-item active">
        <a class="nav-link" href="/horizon">Horizon</span></a>
      </li>
      @if (isset($document_download))
      <li class="nav-item">
        <a class="nav-link" href="{{ $document_download }}">
          <svg width="32px" height="32px" version="1.1" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
          <path d="m35.199 53.398c-0.30078 0.89844-0.30078 0.89844-0.89844 2.3984l-0.10156 0.19922c-0.69922 1.8984-1.8984 4.1992-2.6992 5.6992-1.1992 0.60156-2.3984 1.1992-4.1016 2.1992-3.6016 2.1016-6.1016 3.6016-7 6-0.5 1.3008-1.6992 4.5 0.89844 6.6016 0.89844 0.80078 2.1016 1.1992 3.1992 1.1992 1.1992 0 2.3008-0.39844 3.1992-1.1992 1.1992-1.1016 1.6992-2.1016 2.6992-4 0.30078-0.5 0.60156-1.1016 1-1.8984 1.6016-3.1016 2.6016-5.1992 2.8008-5.5 0-0.10156 0.10156-0.19922 0.19922-0.30078 0.69922-0.30078 1.5-0.60156 2.3984-1 3.3008-1.3984 4.6992-1.8008 7.6992-2.8008l1.3984-0.5c1.1016-0.39844 1.8984-0.60156 2.6992-0.89844 1-0.39844 1.8008-0.60156 2.6016-0.89844 0.69922 0.60156 1.3984 1.3008 2.3008 2 1.8984 1.6016 3.1992 2.3984 4.6016 2.8008 1.1992 0.30078 2.8984 0.19922 4-0.10156 3.5-0.89844 3.6016-3.5 3.6992-4.6016 0.10156-1.5-0.80078-4.1016-4.6992-4.8008-1.8008-0.30078-3-0.30078-4.1016-0.30078-1.1016 0-2 0.10156-3.3984 0.30078-0.39844 0.10156-0.89844 0.19922-1.1992 0.19922-1-1-1.6992-2-2.8008-3.5-2.1016-3-3.3984-5.1016-5.5-8.8008-0.30078-0.5-0.60156-1.1016-0.89844-1.6016 0.19922-0.89844 0.39844-1.8984 0.60156-3.1016l0.30078-1.3984c0.80078-3.8008 1-5.1016 0.39844-8.1992-0.69922-3.3984-3-4.1992-4.1992-4.1016-0.60156 0-3 0.39844-4 3.8984-0.69922 2.3984-0.60156 4.1992 0 6.6992 0.39844 1.8008 1.3008 4 2.8008 6.6992-0.5 2.1016-1.1016 4-2.1992 7.1992-1 3.3125-1.5 4.6133-1.6992 5.4102zm-7.3008 15.203c-0.39844 0.69922-0.69922 1.3984-1 1.8984-1 1.8984-1.1992 2.3008-1.8008 2.8984-0.39844 0.30078-0.89844 0.10156-1.1992-0.10156-0.10156-0.10156-0.39844-0.30078 0.30078-2.1016 0.39844-1 2.3008-2.1992 4-3.3008 0 0.30469-0.19922 0.50391-0.30078 0.70703zm29.301-11c1 0 2 0 3.3984 0.30078 1 0.19922 1.3008 0.60156 1.3008 0.69922v0.69922s-0.10156 0.10156-0.60156 0.19922c-0.69922 0.19922-1.6992 0.19922-2 0.10156-0.5-0.19922-1.3008-0.5-3-1.8984 0.30469-0.10156 0.60156-0.10156 0.90234-0.10156zm-16.898-29.203c0.10156-0.30078 0.19922-0.60156 0.30078-0.69922 0.10156 0.10156 0.10156 0.30078 0.19922 0.60156 0.39844 2.1992 0.39844 2.8984-0.19922 5.8008-0.10156-0.30078-0.19922-0.60156-0.19922-0.80078-0.50391-2.1992-0.60156-3.3008-0.10156-4.9023zm-2.3008 29 0.10156-0.19922c0.60156-1.6016 0.60156-1.6016 1-2.6016 0.19922-0.69922 0.69922-2.1016 1.8008-5.3008 0.5-1.3008 0.80078-2.5 1.1016-3.5 1.5 2.6016 2.6992 4.6016 4.5 7.1016 0.69922 0.89844 1.1992 1.6992 1.8008 2.3984-0.30078 0.10156-0.60156 0.19922-0.89844 0.30078-0.69922 0.30078-1.5 0.5-2.6016 0.89844l-1.4062 0.50391c-2.5 0.80078-3.8984 1.3008-6.1992 2.1992 0.30078-0.59766 0.5-1.1992 0.80078-1.8008zm34.5-0.19922v9.6016h-12.898c-3.8008 0-7 3.1992-7 7v13.199h-30.203c-4.3984 0-7.8984-3.6016-7.8984-8.1016v-57.797c0-4.3984 3.6016-8.1016 7.8984-8.1016h42.199c3.8008 0 7 2.8008 7.8008 6.5 0.69922-0.10156 1.5-0.10156 2.1992-0.10156 0.60156 0 1.1992 0 1.8008 0.10156-0.79688-5.8984-5.7969-10.5-11.898-10.5h-42.102c-6.6016 0-11.898 5.3984-11.898 12.102v57.898c0 6.6992 5.3008 12.102 11.898 12.102h32.199 0.39844c0.10156 0 0.10156 0 0.19922-0.10156 0.10156 0 0.10156 0 0.19922-0.10156 0.10156 0 0.10156-0.10156 0.19922-0.10156 0 0 0.10156 0 0.10156-0.10156 0.10156-0.10156 0.19922-0.19922 0.30078-0.30078l19.801-20.102s0-0.10156 0.10156-0.10156l0.19922-0.19922c0-0.10156 0.10156-0.10156 0.10156-0.19922 0-0.10156 0.10156-0.10156 0.10156-0.19922 0-0.10156 0-0.10156 0.10156-0.19922v-0.19922-0.39844l-0.003906-11.598c-0.60156 0.10156-1.3008 0.10156-1.8984 0.10156-0.69922 0-1.3984 0-2-0.10156zm-15.898 26.902v-10.203c0-1.6992 1.3008-3 3-3h10.102zm17.898-62.301c-9.1016 0-16.5 7.3984-16.5 16.5 0 9.1016 7.3984 16.5 16.5 16.5s16.5-7.4023 16.5-16.5c0-9.1016-7.3984-16.5-16.5-16.5zm0 30c-7.3984 0-13.5-6.1016-13.5-13.5 0-7.3984 6.1016-13.5 13.5-13.5s13.5 6.0977 13.5 13.5c0 7.3984-6 13.5-13.5 13.5zm6.1016-12c0.30078 0.30078 0.39844 0.69922 0.39844 1.1016 0 0.39844-0.10156 0.80078-0.39844 1.1016l-5 5c-0.30078 0.30078-0.60156 0.39844-1 0.39844h-0.10156-0.19922c-0.30078 0-0.60156-0.19922-0.80078-0.39844l-5.1016-5.1016c-0.60156-0.60156-0.60156-1.5 0-2.1016 0.60156-0.60156 1.5-0.60156 2.1016 0l2.3984 2.3984v-11.398c0-0.80078 0.69922-1.5 1.5-1.5 0.80078 0 1.6016 0.69922 1.6016 1.5v11.398l2.3984-2.3984c0.70312-0.60156 1.6016-0.60156 2.2031 0z"  fill="#fff"/>
          </svg>
        </a>
      </li>
      <!-- <li class="nav-item">
        <a class="nav-link" href="{{ $document_download }}">
        <svg width="32px" height="32px" version="1.1" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
        <path d="m35.199 53.398c-0.30078 0.89844-0.30078 0.89844-0.89844 2.3984l-0.10156 0.19922c-0.69922 1.8984-1.8984 4.1992-2.6992 5.6992-1.1992 0.60156-2.3984 1.1992-4.1016 2.1992-3.6016 2.1016-6.1016 3.6016-7 6-0.5 1.3008-1.6992 4.5 0.89844 6.6016 0.89844 0.80078 2.1016 1.1992 3.1992 1.1992 1.1992 0 2.3008-0.39844 3.1992-1.1992 1.1992-1.1016 1.6992-2.1016 2.6992-4 0.30078-0.5 0.60156-1.1016 1-1.8984 1.6016-3.1016 2.6016-5.1992 2.8008-5.5 0-0.10156 0.10156-0.19922 0.19922-0.30078 0.69922-0.30078 1.5-0.60156 2.3984-1 3.3008-1.3984 4.6992-1.8008 7.6992-2.8008l1.3984-0.5c1.1016-0.39844 1.8984-0.60156 2.6992-0.89844 1-0.39844 1.8008-0.60156 2.6016-0.89844 0.69922 0.60156 1.3984 1.3008 2.3008 2 1.8984 1.6016 3.1992 2.3984 4.6016 2.8008 1.1992 0.30078 2.8984 0.19922 4-0.10156 3.5-0.89844 3.6016-3.5 3.6992-4.6016 0.10156-1.5-0.80078-4.1016-4.6992-4.8008-1.8008-0.30078-3-0.30078-4.1016-0.30078-1.1016 0-2 0.10156-3.3984 0.30078-0.39844 0.10156-0.89844 0.19922-1.1992 0.19922-1-1-1.6992-2-2.8008-3.5-2.1016-3-3.3984-5.1016-5.5-8.8008-0.30078-0.5-0.60156-1.1016-0.89844-1.6016 0.19922-0.89844 0.39844-1.8984 0.60156-3.1016l0.30078-1.3984c0.80078-3.8008 1-5.1016 0.39844-8.1992-0.69922-3.3984-3-4.1992-4.1992-4.1016-0.60156 0-3 0.39844-4 3.8984-0.69922 2.3984-0.60156 4.1992 0 6.6992 0.39844 1.8008 1.3008 4 2.8008 6.6992-0.5 2.1016-1.1016 4-2.1992 7.1992-1 3.3125-1.5 4.6133-1.6992 5.4102zm-7.3008 15.203c-0.39844 0.69922-0.69922 1.3984-1 1.8984-1 1.8984-1.1992 2.3008-1.8008 2.8984-0.39844 0.30078-0.89844 0.10156-1.1992-0.10156-0.10156-0.10156-0.39844-0.30078 0.30078-2.1016 0.39844-1 2.3008-2.1992 4-3.3008 0 0.30469-0.19922 0.50391-0.30078 0.70703zm29.301-11c1 0 2 0 3.3984 0.30078 1 0.19922 1.3008 0.60156 1.3008 0.69922v0.69922s-0.10156 0.10156-0.60156 0.19922c-0.69922 0.19922-1.6992 0.19922-2 0.10156-0.5-0.19922-1.3008-0.5-3-1.8984 0.30469-0.10156 0.60156-0.10156 0.90234-0.10156zm-16.898-29.203c0.10156-0.30078 0.19922-0.60156 0.30078-0.69922 0.10156 0.10156 0.10156 0.30078 0.19922 0.60156 0.39844 2.1992 0.39844 2.8984-0.19922 5.8008-0.10156-0.30078-0.19922-0.60156-0.19922-0.80078-0.50391-2.1992-0.60156-3.3008-0.10156-4.9023zm-2.3008 29 0.10156-0.19922c0.60156-1.6016 0.60156-1.6016 1-2.6016 0.19922-0.69922 0.69922-2.1016 1.8008-5.3008 0.5-1.3008 0.80078-2.5 1.1016-3.5 1.5 2.6016 2.6992 4.6016 4.5 7.1016 0.69922 0.89844 1.1992 1.6992 1.8008 2.3984-0.30078 0.10156-0.60156 0.19922-0.89844 0.30078-0.69922 0.30078-1.5 0.5-2.6016 0.89844l-1.4062 0.50391c-2.5 0.80078-3.8984 1.3008-6.1992 2.1992 0.30078-0.59766 0.5-1.1992 0.80078-1.8008zm34.5-0.19922v9.6016h-12.898c-3.8008 0-7 3.1992-7 7v13.199h-30.203c-4.3984 0-7.8984-3.6016-7.8984-8.1016v-57.797c0-4.3984 3.6016-8.1016 7.8984-8.1016h42.199c3.8008 0 7 2.8008 7.8008 6.5 0.69922-0.10156 1.5-0.10156 2.1992-0.10156 0.60156 0 1.1992 0 1.8008 0.10156-0.79688-5.8984-5.7969-10.5-11.898-10.5h-42.102c-6.6016 0-11.898 5.3984-11.898 12.102v57.898c0 6.6992 5.3008 12.102 11.898 12.102h32.199 0.39844c0.10156 0 0.10156 0 0.19922-0.10156 0.10156 0 0.10156 0 0.19922-0.10156 0.10156 0 0.10156-0.10156 0.19922-0.10156 0 0 0.10156 0 0.10156-0.10156 0.10156-0.10156 0.19922-0.19922 0.30078-0.30078l19.801-20.102s0-0.10156 0.10156-0.10156l0.19922-0.19922c0-0.10156 0.10156-0.10156 0.10156-0.19922 0-0.10156 0.10156-0.10156 0.10156-0.19922 0-0.10156 0-0.10156 0.10156-0.19922v-0.19922-0.39844l-0.003906-11.598c-0.60156 0.10156-1.3008 0.10156-1.8984 0.10156-0.69922 0-1.3984 0-2-0.10156zm-15.898 26.902v-10.203c0-1.6992 1.3008-3 3-3h10.102zm17.898-62.301c-9.1016 0-16.5 7.3984-16.5 16.5 0 9.1016 7.3984 16.5 16.5 16.5s16.5-7.4023 16.5-16.5c0-9.1016-7.3984-16.5-16.5-16.5zm0 30c-7.3984 0-13.5-6.1016-13.5-13.5 0-7.3984 6.1016-13.5 13.5-13.5s13.5 6.0977 13.5 13.5c0 7.3984-6 13.5-13.5 13.5zm2.5-10.5h-1.5c-0.30078 0-0.5-0.19922-0.5-0.5s0.19922-0.5 0.5-0.5h1v-1c0-0.30078 0.19922-0.5 0.5-0.5s0.5 0.19922 0.5 0.5v1.5c0 0.30078-0.19922 0.5-0.5 0.5zm-2-5.5c0-0.30078 0.19922-0.5 0.5-0.5h1.5c0.30078 0 0.5 0.19922 0.5 0.5v1.3984c0 0.30078-0.19922 0.5-0.5 0.5s-0.5-0.19922-0.5-0.5v-0.89844h-1c-0.19922 0-0.5-0.19922-0.5-0.5zm-0.89844 0c0 0.30078-0.19922 0.5-0.5 0.5h-1v0.89844c0 0.30078-0.19922 0.5-0.5 0.5-0.30078 0-0.5-0.19922-0.5-0.5v-1.3984c0-0.30078 0.19922-0.5 0.5-0.5h1.5c0.19922 0 0.5 0.30078 0.5 0.5zm0 5c0 0.30078-0.19922 0.5-0.5 0.5h-1.6016c-0.30078 0-0.5-0.19922-0.5-0.5v-1.5c0-0.30078 0.19922-0.5 0.5-0.5s0.5 0.19922 0.5 0.5v1h1c0.30078 0 0.60156 0.30078 0.60156 0.5zm8.3984 0.5v4.5c0 0.30078-0.19922 0.5-0.5 0.5h-4.5c-0.30078 0-0.5-0.19922-0.5-0.5s0.19922-0.5 0.5-0.5h4v-4c0-0.30078 0.19922-0.5 0.5-0.5s0.5 0.19922 0.5 0.5zm0-10.5v4.1992c0 0.30078-0.19922 0.5-0.5 0.5s-0.5-0.19922-0.5-0.5v-3.6992h-4c-0.30078 0-0.5-0.19922-0.5-0.5s0.19922-0.5 0.5-0.5h4.5c0.30078 0 0.5 0.30078 0.5 0.5zm-16 4.1992v-4.1992c0-0.30078 0.19922-0.5 0.5-0.5h4.5c0.30078 0 0.5 0.19922 0.5 0.5s-0.19922 0.5-0.5 0.5h-4v3.6992c0 0.30078-0.19922 0.5-0.5 0.5s-0.5-0.19922-0.5-0.5zm5.6016 10.801c0 0.30078-0.19922 0.5-0.5 0.5h-4.6016c-0.30078 0-0.5-0.19922-0.5-0.5v-4.5c0-0.30078 0.19922-0.5 0.5-0.5s0.5 0.19922 0.5 0.5v4h4c0.39844 0 0.60156 0.30078 0.60156 0.5z" fill="#fff"/>
        </svg>
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link" href="{{ $document_download }}">
        <svg width="32px" height="32px" version="1.1" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
          <path d="m35.199 53.398c-0.30078 0.89844-0.30078 0.89844-0.89844 2.3984l-0.10156 0.19922c-0.69922 1.8984-1.8984 4.1992-2.6992 5.6992-1.1992 0.60156-2.3984 1.1992-4.1016 2.1992-3.6016 2.1016-6.1016 3.6016-7 6-0.5 1.3008-1.6992 4.5 0.89844 6.6016 0.89844 0.80078 2.1016 1.1992 3.1992 1.1992 1.1992 0 2.3008-0.39844 3.1992-1.1992 1.1992-1.1016 1.6992-2.1016 2.6992-4 0.30078-0.5 0.60156-1.1016 1-1.8984 1.6016-3.1016 2.6016-5.1992 2.8008-5.5 0-0.10156 0.10156-0.19922 0.19922-0.30078 0.69922-0.30078 1.5-0.60156 2.3984-1 3.3008-1.3984 4.6992-1.8008 7.6992-2.8008l1.3984-0.5c1.1016-0.39844 1.8984-0.60156 2.6992-0.89844 1-0.39844 1.8008-0.60156 2.6016-0.89844 0.69922 0.60156 1.3984 1.3008 2.3008 2 1.8984 1.6016 3.1992 2.3984 4.6016 2.8008 1.1992 0.30078 2.8984 0.19922 4-0.10156 3.5-0.89844 3.6016-3.5 3.6992-4.6016 0.10156-1.5-0.80078-4.1016-4.6992-4.8008-1.8008-0.30078-3-0.30078-4.1016-0.30078-1.1016 0-2 0.10156-3.3984 0.30078-0.39844 0.10156-0.89844 0.19922-1.1992 0.19922-1-1-1.6992-2-2.8008-3.5-2.1016-3-3.3984-5.1016-5.5-8.8008-0.30078-0.5-0.60156-1.1016-0.89844-1.6016 0.19922-0.89844 0.39844-1.8984 0.60156-3.1016l0.30078-1.3984c0.80078-3.8008 1-5.1016 0.39844-8.1992-0.69922-3.3984-3-4.1992-4.1992-4.1016-0.60156 0-3 0.39844-4 3.8984-0.69922 2.3984-0.60156 4.1992 0 6.6992 0.39844 1.8008 1.3008 4 2.8008 6.6992-0.5 2.1016-1.1016 4-2.1992 7.1992-1 3.3125-1.5 4.6133-1.6992 5.4102zm-7.3008 15.203c-0.39844 0.69922-0.69922 1.3984-1 1.8984-1 1.8984-1.1992 2.3008-1.8008 2.8984-0.39844 0.30078-0.89844 0.10156-1.1992-0.10156-0.10156-0.10156-0.39844-0.30078 0.30078-2.1016 0.39844-1 2.3008-2.1992 4-3.3008 0 0.30469-0.19922 0.50391-0.30078 0.70703zm29.301-11c1 0 2 0 3.3984 0.30078 1 0.19922 1.3008 0.60156 1.3008 0.69922v0.69922s-0.10156 0.10156-0.60156 0.19922c-0.69922 0.19922-1.6992 0.19922-2 0.10156-0.5-0.19922-1.3008-0.5-3-1.8984 0.30469-0.10156 0.60156-0.10156 0.90234-0.10156zm-16.898-29.203c0.10156-0.30078 0.19922-0.60156 0.30078-0.69922 0.10156 0.10156 0.10156 0.30078 0.19922 0.60156 0.39844 2.1992 0.39844 2.8984-0.19922 5.8008-0.10156-0.30078-0.19922-0.60156-0.19922-0.80078-0.50391-2.1992-0.60156-3.3008-0.10156-4.9023zm-2.3008 29 0.10156-0.19922c0.60156-1.6016 0.60156-1.6016 1-2.6016 0.19922-0.69922 0.69922-2.1016 1.8008-5.3008 0.5-1.3008 0.80078-2.5 1.1016-3.5 1.5 2.6016 2.6992 4.6016 4.5 7.1016 0.69922 0.89844 1.1992 1.6992 1.8008 2.3984-0.30078 0.10156-0.60156 0.19922-0.89844 0.30078-0.69922 0.30078-1.5 0.5-2.6016 0.89844l-1.4062 0.50391c-2.5 0.80078-3.8984 1.3008-6.1992 2.1992 0.30078-0.59766 0.5-1.1992 0.80078-1.8008zm34.5-0.19922v9.6016h-12.898c-3.8008 0-7 3.1992-7 7v13.199h-30.203c-4.3984 0-7.8984-3.6016-7.8984-8.1016v-57.797c0-4.3984 3.6016-8.1016 7.8984-8.1016h42.199c3.8008 0 7 2.8008 7.8008 6.5 0.69922-0.10156 1.5-0.10156 2.1992-0.10156 0.60156 0 1.1992 0 1.8008 0.10156-0.79688-5.8984-5.7969-10.5-11.898-10.5h-42.102c-6.6016 0-11.898 5.3984-11.898 12.102v57.898c0 6.6992 5.3008 12.102 11.898 12.102h32.199 0.39844c0.10156 0 0.10156 0 0.19922-0.10156 0.10156 0 0.10156 0 0.19922-0.10156 0.10156 0 0.10156-0.10156 0.19922-0.10156 0 0 0.10156 0 0.10156-0.10156 0.10156-0.10156 0.19922-0.19922 0.30078-0.30078l19.801-20.102s0-0.10156 0.10156-0.10156l0.19922-0.19922c0-0.10156 0.10156-0.10156 0.10156-0.19922 0-0.10156 0.10156-0.10156 0.10156-0.19922 0-0.10156 0-0.10156 0.10156-0.19922v-0.19922-0.39844l-0.003906-11.598c-0.60156 0.10156-1.3008 0.10156-1.8984 0.10156-0.69922 0-1.3984 0-2-0.10156zm-15.898 26.902v-10.203c0-1.6992 1.3008-3 3-3h10.102zm17.898-62.301c-9.1016 0-16.5 7.3984-16.5 16.5 0 9.1016 7.3984 16.5 16.5 16.5s16.5-7.4023 16.5-16.5c0-9.1016-7.3984-16.5-16.5-16.5zm0 30c-7.3984 0-13.5-6.1016-13.5-13.5 0-7.3984 6.1016-13.5 13.5-13.5s13.5 6.0977 13.5 13.5c0 7.3984-6 13.5-13.5 13.5zm6.1016-12c0.30078 0.30078 0.39844 0.69922 0.39844 1.1016 0 0.39844-0.10156 0.80078-0.39844 1.1016l-5 5c-0.30078 0.30078-0.60156 0.39844-1 0.39844h-0.10156-0.19922c-0.30078 0-0.60156-0.19922-0.80078-0.39844l-5.1016-5.1016c-0.60156-0.60156-0.60156-1.5 0-2.1016 0.60156-0.60156 1.5-0.60156 2.1016 0l2.3984 2.3984v-11.398c0-0.80078 0.69922-1.5 1.5-1.5 0.80078 0 1.6016 0.69922 1.6016 1.5v11.398l2.3984-2.3984c0.70312-0.60156 1.6016-0.60156 2.2031 0z"  fill="#fff"/>
        </svg>
        </a>
      </li> -->
      @endif
<!--       <li class="nav-item">
        <a class="nav-link" href="#">Link</a>
      </li>
      <li class="nav-item">
        <a class="nav-link disabled" href="#" tabindex="-1" aria-disabled="true">Disabled</a>
      </li>
      <li class="nav-item dropdown">
        <a class="nav-link dropdown-toggle" href="#" id="dropdown01" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">Dropdown</a>
        <div class="dropdown-menu" aria-labelledby="dropdown01">
          <a class="dropdown-item" href="#">Action</a>
          <a class="dropdown-item" href="#">Another action</a>
          <a class="dropdown-item" href="#">Something else here</a>
        </div>
      </li> -->
    </ul>

    <form class="form-inline d-flex ms-3" method="GET" action="{{ route('search') }}">

      <div class="input-group ">
        <div class="input-group-prepend">
        @if (isset ($system) && $system)
          <div class="input-group-text">{{ $system }}</div>
          <input type="hidden" name="s" value="{{ $system }}">
        @else
          <div class="input-group-text"><i class="bi bi-search"></i></div>
        @endisset
        </div>
      <input class="form-control" type="text" name="q" placeholder="Search" aria-label="Search">
      </div>
        <button class="btn btn-secondary" type="submit">Search</button>
    </form>

    <!-- Right Side Of Navbar -->
    <ul class="navbar-nav ms-auto">
        <!-- Authentication Links -->
        @guest
            @if (Route::has('login'))
                <li class="nav-item">
                    <a class="nav-link" href="{{ route('login') }}">{{ __('Login') }}</a>
                </li>
            @endif

            @if (Route::has('register'))
                <li class="nav-item">
                    <a class="nav-link" href="{{ route('register') }}">{{ __('Register') }}</a>
                </li>
            @endif
        @else
            <li class="nav-item dropdown">
                <a id="navbarDropdown" class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false" v-pre>
                    {{ Auth::user()->name }}
                </a>

                <div class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                    <a class="dropdown-item" href="{{ route('logout') }}"
                        onclick="event.preventDefault();
                                        document.getElementById('logout-form').submit();">
                        {{ __('Logout') }}
                    </a>

                    <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
                        @csrf
                    </form>
                </div>
            </li>
        @endguest
    </ul>
  </div>
</nav>

@hasSection('full_page')
        @yield('full_page')
@endif
@sectionMissing('full_page')
<main role="main" class="container">

        <div class="container">
            @yield('content')
        </div>
@endif


</main><!-- /.container -->
<script src="js/app.js" charset="utf-8"></script>


</body>
</html>
