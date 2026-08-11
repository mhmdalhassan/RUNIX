@props([
    'label' => null,
    'place_holder' => null,
    'name' => null,
    'value' => null,
    'id' => null,
    'disabled'=>'false',
    'class' => null,
    'minDate' => null,
    'defaultDate' => null,
])

@php
    $id = $id ?? uniqid();
@endphp
<label for="{{$id}}" class="form-label">{{$label}}</label>
<input type="text" autocomplete="off" @if($disabled=='true') disabled @endif class="form-control flatpickr-date  {{$class ??''}}" placeholder="{{$place_holder}}" id="{{$id}}" name="{{$name}}" value="{{$value}}">

@push('page-script')
  <script>
    var flatpickrDate = $('.flatpickr-date');
    if (flatpickrDate) {
      flatpickrDate.flatpickr({
        monthSelectorType: 'dropdown',
        allowInput: true,
        minDate: '{{ \Carbon\Carbon::parse($minDate)->format('Y-m-d') }}',
        defaultDate: '{{$defaultDate ?? ''}}',
        onOpen: function(selectedDates, dateStr, instance) {
          function getAbsolutePosition(el) {
            let left = 0, top = 0;
            while (el) {
              left += el.offsetLeft - el.scrollLeft + el.clientLeft;
              top += el.offsetTop - el.scrollTop + el.clientTop;
              el = el.offsetParent;
            }
            return { top, left };
          }
          const inputEl = instance._input;
          const calendar = instance.calendarContainer;

          // Get absolute position of input
          const position = getAbsolutePosition(inputEl);

          setTimeout(() => {
            // Move the calendar popup
            calendar.style.position = "absolute";
            calendar.style.left = position.left + "px";
            calendar.style.top = (position.top + inputEl.offsetHeight) + "px";
          }, 10);
        }
      });
    }
  </script>
@endpush
