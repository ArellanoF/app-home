<div style="border-left:3px solid {{ $accent }};padding:9px 12px;margin-bottom:8px;background:#f8f8f5">
    <strong style="display:block;font-size:14px">{{ $task->title }}</strong>
    <span style="display:block;margin-top:4px;color:#69736d;font-size:12px">
        {{ $task->due_date ? $task->due_date->locale('es')->isoFormat('dddd D [de] MMMM') : 'Sin fecha limite' }}
    </span>
    @if ($task->description)
        <span style="display:block;margin-top:5px;color:#69736d;font-size:12px;line-height:1.4">{{ $task->description }}</span>
    @endif
</div>
