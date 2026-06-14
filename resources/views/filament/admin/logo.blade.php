@if (auth()->guest())
    <div class="py-6 flex justify-center">
        <img src="{{ asset('images/zioto-logo.png') }}" alt="Zioto"
             class="h-10 sm:h-12 w-auto">
    </div>
@else
    <div class="flex items-center h-full">
        <img src="{{ asset('images/zioto-logo.png') }}" alt="Zioto"
             class="h-7 sm:h-8 lg:h-9 w-auto"
             style="margin-top: 5px;">
    </div>
@endif
