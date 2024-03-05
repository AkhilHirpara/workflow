<p>
    <img src="{{url('public/images/Quadrin_logo_Strapline_RGB.png')}}" alt="{{env('APP_NAME')}}" />
</p>
<hr>
<p>Hello Admin,</p>
<p>
    The following user/users haven't deleted the files after 3 warnings or haven't confirmed the deletion of files.
</p>
<p>
    <ul>
    @foreach($maildata['usernames'] as $each_user)
        <li>{{$each_user}}</li>
    @endforeach
    </ul>
</p>
<br>

<br>
<br>
Best regards,
<br>
{{env('APP_NAME')}}