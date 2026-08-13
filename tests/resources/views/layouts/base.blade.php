<!doctype html>
<html>
<head>
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title')</title>
</head>
<body>
@yield('breadcrumb')
@yield('menu')
@yield('buttons')
@yield('raccoon-layouts')
@yield('content')
</body>
</html>
