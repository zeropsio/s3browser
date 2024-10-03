<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>S3 Bucket Files</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 p-5">
<a href="/"><img class="max-h-12 block mx-auto mb-4" src="/images/zerops-logo.svg" /></a>
<h1 class="text-3xl font-bold text-center text-gray-800">Zerops S3 Bucket Browser</h1>
<h2 class="text-xl text-center text-gray-600">Number of files: {{ $totalFileCount }} | Bucket name: {{$connectionInfo['bucket_name']}}</h2>

<div class="flex justify-end mb-4">
    <form method="POST" action="{{ route('upload.test.file') }}" id="uploadTestFileForm" class="flex items-center">
        @csrf <!-- Include CSRF token -->
        <button type="submit" class="bg-blue-500 text-white p-2 rounded hover:bg-blue-600">
            Upload Test File
        </button>
    </form>
</div>

<div id="uploadMessage" class="mt-4 text-green-500"></div>

<div class="flex justify-end mb-4">

    <form method="GET" action="{{ route('index') }}" class="flex items-center">
{{--        <span>It's boroken!!! </span>--}}
        <label for="sortBy" class="font-bold mr-2">Sort by:</label>
        <select name="sortBy" id="sortBy" onchange="this.form.submit()" class="p-2 border border-gray-300 rounded">
            <option value="created_at" {{ request('sortBy') === 'created_at' ? 'selected' : '' }}>Date Created</option>
            <option value="name" {{ request('sortBy') === 'name' ? 'selected' : '' }}>Name</option>
            <option value="size" {{ request('sortBy') === 'size' ? 'selected' : '' }}>Size</option>
        </select>

        <select name="sortOrder" id="sortOrder" onchange="this.form.submit()" class="p-2 border border-gray-300 rounded ml-2">
            <option value="desc" {{ request('sortOrder') === 'desc' ? 'selected' : '' }}>Descending</option>
            <option value="asc" {{ request('sortOrder') === 'asc' ? 'selected' : '' }}>Ascending</option>
        </select>
    </form>
</div>

<div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-8 lg:grid-cols-12 gap-4">
    @foreach ($paginatedFiles as $file)
        <div class="bg-white rounded-lg shadow-md p-4 flex flex-col justify-between">
            @php
                $isImage = app('App\Http\Controllers\S3Controller')->isImage($file);
                $fileUrl = Storage::disk('s3')->url($file['path']);
                $formattedSize = app('App\Http\Controllers\S3Controller')->formatSizeUnits($file['size']);
                $lastModified = \Carbon\Carbon::parse($file['lastModified'])->format('d M Y H:i:s');
            @endphp

            {{-- File Preview Section --}}
            <div>
                @if ($isImage)
                    <img src="{{ $fileUrl }}" alt="{{ $file['path'] }}" class="max-w-full h-auto rounded">
                @else
                    {{-- Show icon based on file type --}}
                    @php
                        $extension = pathinfo($file['path'], PATHINFO_EXTENSION);
                    @endphp
                    @switch($extension)
                        @case('pdf')
                            <div class="text-6xl text-gray-500">📄</div>
                            @break
                        @case('doc')
                        @case('docx')
                            <div class="text-6xl text-gray-500">📝</div>
                            @break
                        @case('xls')
                        @case('xlsx')
                            <div class="text-6xl text-gray-500">📊</div>
                            @break
                        @case('zip')
                        @case('rar')
                            <div class="text-6xl text-gray-500">🗜️</div>
                            @break
                        @default
                            <div class="text-6xl text-gray-500">📁</div>
                    @endswitch
                @endif

                <p class="text-sm text-gray-600 mt-2 break-words">{{ $file['path'] }}</p>
                <p class="text-sm text-gray-500 mt-1">{{ $formattedSize }}</p>
                <p class="text-sm text-gray-400 mt-1">Last Modified: {{ $lastModified }}</p>
            </div>

            {{-- Action Buttons Section --}}
            <div class="mt-2 flex justify-between items-center space-x-2 w-full pt-4 border-t border-gray-200">
                {{-- View Button --}}
                <a href="{{ $fileUrl }}" target="_blank" class="bg-blue-500 text-white py-2 px-4 rounded flex-1 text-center hover:bg-blue-600 text-sm">
                    View
                </a>

                {{-- Delete Button with Icon --}}
                <form method="POST" action="{{ route('delete.file') }}" class="deleteFileForm flex-1">
                    @csrf
                    @method('DELETE')
                    <input type="hidden" name="filePath" value="{{ $file['path'] }}">
                    <button type="submit" class="bg-red-500 text-white py-2 px-4 rounded w-full flex items-center justify-center hover:bg-red-600 text-sm">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </form>
            </div>
        </div>
    @endforeach
</div>


{{-- Pagination --}}
<div class="mt-5">
        {{ $paginatedFiles->links('vendor.pagination.tailwind') }}

    @if($nextContinuationToken)
        <div class="mt-5 flex justify-center">
            <form method="GET" action="{{ route('index') }}">
                <input type="hidden" name="continuationToken" value="{{ $nextContinuationToken }}">
                <button type="submit" class="bg-blue-500 text-white p-2 rounded">Load More Files</button>
            </form>
        </div>
    @endif
</div>

<script>
    document.getElementById('uploadTestFileForm').addEventListener('submit', function(event) {
        event.preventDefault(); // Prevent the form from submitting normally

        const formData = new FormData(this);

        fetch(this.action, {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}' // Include CSRF token
            }
        })
            .then(response => response.json())
            .then(data => {
                document.getElementById('uploadMessage').innerText = data.message + ': ' + data.file_name;
                document.location.href="/";
            })
            .catch(error => {
                document.getElementById('uploadMessage').innerText = 'Error uploading file';
            });
    });

    document.querySelectorAll('.deleteFileForm').forEach(form => {
        form.addEventListener('submit', function(event) {
            event.preventDefault(); // Prevent normal form submission

            const formData = new FormData(this);
            console.log('Form Data:', Array.from(formData.entries())); // Log the form data to ensure it's being captured

            // if (confirm('Are you sure you want to delete this file?')) {
            if (1) {
                fetch(this.action, {
                    method: "POST",
                    body: formData,
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    }
                })
                    .then(response => response.json())
                    .then(data => {
                        console.log('Server Response:', data); // Log the server response
                        // alert(data.message);
                        document.location.href="/";
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Error deleting file');
                    });
            }
        });
    });


</script>
</body>
</html>

