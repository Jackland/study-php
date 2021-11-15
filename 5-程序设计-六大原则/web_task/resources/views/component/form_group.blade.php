<div class="form-group {{  $class ?? ''  }}">
    {{ $label  ?? ''  }}
    <div class="{{ $ele_class ?? 'col-md-6'  }}">
        {{ $slot }}
    </div>
</div>