<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[#FFFFFF] min-h-screen flex items-center justify-center">

<div class="w-[1000px] h-[550px] bg-white rounded-3xl 
            border border-gray-200 
            shadow-[0_10px_40px_rgba(0,0,0,0.15)] 
            flex overflow-hidden">

    <!-- LEFT -->
    <div class="w-1/2 relative flex flex-col justify-between text-white p-10 bg-cover bg-center"
     style="background-image: url('/images/living-lab.jpg')">

        <!-- overlay biar teks kebaca -->
        <div class="absolute inset-0 bg-teal-900/30"></div>

        <!-- TEXT -->
        <div class="relative z-10 mt-auto">
            <h1 class="text-2xl font-bold leading-snug">
                TURN WASTE INTO <br>
                SOMETHING POWERFUL
            </h1>

            <p class="text-sm mt-3 opacity-80">
                Experience the power of eco enzyme fermentation in our living lab.
            </p>
            </p>
        </div>
    </div>

    <!-- RIGHT -->
    <div class="w-1/2 p-12 flex flex-col justify-center bg-gray-50">

        <h2 class="text-2xl font-semibold text-teal-700 mb-8 text-center">
            LOGIN
        </h2>

        <form method="POST" action="{{ route('login.post') }}" class="space-y-6">
            @csrf

            <div>
                <label class="text-sm text-gray-600">Email</label>
                <input type="email" name="email"
                    class="w-full border rounded-lg px-4 py-2 mt-1 focus:outline-none focus:ring-2 focus:ring-teal-500">
            </div>

            <div>
                <label class="text-sm text-gray-600">Password</label>
                <input type="password" name="password"
                    class="w-full border rounded-lg px-4 py-2 mt-1 focus:outline-none focus:ring-2 focus:ring-teal-500">
            </div>

            <button class="w-full bg-[#126212] text-white py-3 rounded-full shadow-md hover:bg-[#0F390F] transition">
                Login
            </button>
        </form>

    </div>
</div>

</body>
</html>