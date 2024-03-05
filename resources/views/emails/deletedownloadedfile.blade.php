<p>
    <img src="{{url('public/images/Quadrin_logo_Strapline_RGB.png')}}" alt="{{env('APP_NAME')}}" />
</p>
<hr>
<p>Hello {{$maildata['fullname']}},</p>
<p>Please delete the downloaded files.</p>
<p>During the previous month files have been downloaded that due to GDPR you now need to upload back to Qflow (if you have changed them) and then delete from your computer.</p>
<p>Below are the list of files that are downloaded by you and not deleted.<br>
    Listed below are the files you downloaded:<br>
    <ul>
    @foreach($maildata['file_list'] as $each_file)
        <li>{{$each_file}}</li>
    @endforeach
    </ul>
</p>
<br>
<p>
    If you have deleted the files then please confirm by clicking on the below button.<br>
    You are required once the above files have been dealt with to click on the “I Confirm” button below, if you do not do this you will continue to receive reminder emails and after 3 emails you line manager will be notified.<br>
    <a style="background-color: #ee7608;border-radius: 50px;border: 0;padding: 10px 20px;color: #fff;display: inline-block;margin-top: 20px;text-decoration: none;" 
    href="{{env('REACT_APP_URL')}}/confirm-delete?token={{$maildata['token']}}">I Confirm</a>
</p>

<br>
<br>
Best regards,
<br>
{{env('APP_NAME')}}