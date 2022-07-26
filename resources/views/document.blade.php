@extends('layouts.app')

@section('title', $title)


@section('full_page')
<style type="text/css">
#pdf-view {
     position: absolute;
     height: 100%;
     width: 100%;
     border: none;
   }

  nav.docbreadcrumb {
  margin-left: 1em;
  padding-left: 1em;
  }
</style>
<div id="container-fluid">
<div class="row">
<nav aria-label="breadcrumb" class="docbreadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item" aria-current="page"><a href="{{ route("home") }}">Systems</a></li>
    <li class="breadcrumb-item active" aria-current="page"><a href="{{ route("system.index", [ 'system' => $display_document['_source']['system'] ]) }}">{{ $display_document['_source']['system'] }}</a></li>
  </ol>
</nav>
  </div>

@if ($adobe_client_id)
    <div id="pdf-view"></div>
    <script src="https://documentcloud.adobe.com/view-sdk/main.js"></script>
    <script type="text/javascript">
      document.addEventListener("adobe_dc_view_sdk.ready", function(){ 
        var adobeDCView = new AdobeDC.View({clientId: "{{ $adobe_client_id }}", divId: "pdf-view"});
        adobeDCView.previewFile({
          content:{location: {url: "{!! $document_download !!}"}},
          metaData:{fileName: "{{ $display_document['_source']['path'] }}"}
        }, {embedMode: "IN_LINE"});
      });
    </script>
@else
  <iframe id="pdf-view" src="{{ $document_download }}"></iframe>
@endif
</div>
@endsection