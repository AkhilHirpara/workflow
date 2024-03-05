<p>
    <img src="{{url('public/images/Quadrin_logo_Strapline.png')}}" alt="{{env('APP_NAME')}}" />
</p>
<hr>
<p>Hello {{$data->firstname}} {{$data->lastname}},</p>
<p>
    Your password reset request has been processed.
</p>
<p>
    Click the link below or copy and paste the URL into your browser to re-set your password.
</p>
<p>
    <a href="{{env('REACT_APP_URL')}}/reset-password?token={{$token}}">{{env('REACT_APP_URL')}}/reset-password?token={{$token}}</a>
</p>
<br>
<br>
Best regards,
<br>
{{env('APP_NAME')}}