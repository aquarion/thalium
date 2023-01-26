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
    <script src="https://documentservices.adobe.com/view-sdk/viewer.js"></script>
    <script type="text/javascript">
      var adobeDCView = false;

      document.addEventListener("adobe_dc_view_sdk.ready", function(){ 
        var adobeDCView = new AdobeDC.View({clientId: "{{ $adobe_client_id }}", divId: "pdf-view"});
        const previewFilePromise = adobeDCView.previewFile({
          content:{location: {url: "{!! $document_download !!}"}},
          metaData:{fileName: "{{ $display_document['_source']['path'] }}"}
        }, {embedMode: "IN_LINE", enableSearchAPIs: true });


        const queryParams = new URLSearchParams(window.location.search);

        var page = queryParams.get("page");
        var search = queryParams.get("search");

        previewFilePromise.then(adobeViewer => {
            adobeViewer.getAPIs().then(apis => {

              if (page){
                page = Number(page);
                apis.gotoLocation(page)
                        .then(() => console.log("Success"))
                        .catch(error => console.log(error));
              }
              if (search){
                apis.search(search)
                        .then(searchObject => console.log(searchObject))
                        .catch(error => console.log(error));
              }

            });
          });

      });
    </script>
@else
  <iframe id="pdf-view" src="{{ $document_download }}"></iframe>
@endif
</div>
@endsection