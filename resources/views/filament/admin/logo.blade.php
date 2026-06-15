@if (auth()->guest())
    <div class="py-6 flex justify-center">
        <img src="{{ asset('images/zioto-logo.png') }}" alt="Zioto"
             class="h-6 sm:h-8 w-auto">
    </div>
@else
    <div class="flex items-center h-full">
        <img src="{{ asset('images/zioto-logo.png') }}" alt="Zioto"
             class="h-4 sm:h-5 lg:h-9 w-auto"
             style="margin-top: 5px;max;width: 150px;height: 25px;">
    </div>
@endif
