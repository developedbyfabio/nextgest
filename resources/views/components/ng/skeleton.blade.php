@props(['rows' => 3, 'height' => 'h-14'])

{{-- Lista de skeletons para estados de carregamento. --}}
<div {{ $attributes->class('flex flex-col gap-2') }} aria-hidden="true">
    @for ($i = 0; $i < (int) $rows; $i++)
        <div class="ng-skeleton {{ $height }} w-full"></div>
    @endfor
</div>
