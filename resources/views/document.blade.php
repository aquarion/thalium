@extends('layouts.app')

@section('title', $title)


@section('full_page')
<style type="text/css">
iframe {
     position: absolute;
     height: 100%;
     width: 100%;
     border: none;
   }
</style>
<div id="container-fluid">
    <iframe src="{{ $document_download }}"></iframe>
</div>
@endsection