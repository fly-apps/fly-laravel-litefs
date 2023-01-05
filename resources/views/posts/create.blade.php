<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  @vite('resources/css/app.css')
</head>
<body>
    <div class="flex flex-col justify-center items-center  pt-20 pb-2">
        <div>
            <h1 class="
            font-extrabold
            text-6xl 
            bg-gradient-to-r
            text-transparent text-8xl bg-clip-text
            from-purple-400 via-pink-500 to-indigo-500">
                Post List
            </h1>

            <table class="pt-20 min-w-full table-auto border-collapse text-center  text-gray-900 bg-gradient-to-r from-lime-200 via-lime-400 to-lime-500 hover:bg-gradient-to-br focus:ring-4 focus:outline-none focus:ring-lime-300 dark:focus:ring-lime-800 font-medium rounded-lg text-sm px-5 py-2.5 text-center mr-2 mb-2">
                <thead class="border-b bg-gray-50">
                    <tr>
                        <th>Title</th>
                        <th>Summary</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($recordList as $record)
                    <tr>
                        <td>{{ $record->title }}</td>
                        <td>{{ $record->body }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <form action="store" method="post" class="pt-10 flex space-x-1" >
            @csrf
            <div>
                <input class="border  border-black @error('title') is-invalid @enderror" type="text" name="title" placeholder="Set your Title" />
                @error('title')<div class="text-red-700  rounded-md">{{ $message }}</div>@enderror
            </div>

            <div>
                <input class="border  border-black @error('body') is-invalid @enderror" type="text" name="body" placeholder="Start your Summary" />
                @error('body')<div class="text-red-700  rounded-md">{{ $message }}</div>@enderror
            </div>

            <button type="submit" class="animate-bounce bg-blue-500 text-white pt-2 pb-2 px-3 rounded-md" >Submit</button>
        </form>
    </div>
</body>
</html>