
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
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent" />
<link rel="apple-touch-startup-image" href="/static/icons/apple-touch-icon.png">
<meta name="mobile-web-app-capable" content="yes">
<link rel="apple-touch-icon" sizes="180x180" href="/static/icons/apple-touch-icon.png">

    <!-- Bootstrap core CSS -->
<link rel="stylesheet" href="/css/app.css">

    <!-- Custom styles for this template -->
    <link href="starter-template.css" rel="stylesheet">
  </head>
  <body>
    <nav class="navbar navbar-expand-md navbar-dark bg-dark fixed-top">
  <a class="navbar-brand" href="/"><img src="/static/thalium_white.png" >Thalium</a>
  <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarsExampleDefault" aria-controls="navbarsExampleDefault" aria-expanded="false" aria-label="Toggle navigation">
    <span class="navbar-toggler-icon"></span>
  </button>

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
        <a class="nav-link" href="{{ $document_download }}"><i class="bi bi-file-earmark-pdf" title="Open PDF Directly"></i></a>
      </li>
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
        @isset ($system)
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
