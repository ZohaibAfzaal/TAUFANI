@props(['value'])

<label {{ $attributes->merge(['class' => 'block text-[11px] font-bold uppercase tracking-widest text-slate-500 mb-1.5']) }}>
    {{ $value ?? $slot }}
</label>
