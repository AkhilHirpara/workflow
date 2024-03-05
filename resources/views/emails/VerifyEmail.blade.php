<p>
    <img src="{{url('/images/Quadrin_logo_Strapline_RGB.svg')}}" alt="{{env('APP_NAME')}}" />
</p>
<hr>
<p>Hello {{$data->firstname}} {{$data->lastname}},</p>
<p>
   Your acccount is successfully created on {{env('APP_NAME')}}.
</p>
<p>
    Click the link below or copy and paste the URL into your browser to verify and activate your account.
</p>
<p>
    <a href="{{env('REACT_APP_URL')}}/verify-user?token={{$token}}">{{env('REACT_APP_URL')}}/verify-user?token={{$token}}</a>
</p>
<br>
<br>
Best regards,
<br>
{{env('APP_NAME')}}