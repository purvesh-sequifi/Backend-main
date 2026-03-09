@extends('layout.mail_layout')

@section('title') 
    What do you want to change
@endsection

{{-- Top head in mail --}}
@section('top_head')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <tr>
<style type="text/css">
    body {
        margin: 0px;
    }
</style>
    <tr>
        <td bgcolor="#ffffff" align="left">
            <div style="padding: 15px 40px;">
                <p style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';color: #767373;
                    font-size: 30px;font-weight: 500; text-align: center;">
                    What do you want to change!
                </p>
            </div>
        </td>
    </tr>
@endsection

{{-- Add icon --}}

@section('icon_section') 
    <tr>
        <td align="center">
            <table border="0" cellspacing="0" cellpadding="0">
                <tr>
                    <td align="center">
                    <table width="5" border="0" align="center" cellpadding="0" cellspacing="0">
                        <tr>
                            <td width="5" height="5" bgcolor="#a8803a" style="border-radius:10px;"></td>
                        </tr>
                        </table>
                    </td>
                    <td width="15"></td>
                    <td align="center">
                        <table width="5" border="0" align="center" cellpadding="0" cellspacing="0">
                        <tr>
                            <td width="5" height="5" bgcolor="#a8803a" style="border-radius:10px;"></td>
                        </tr>
                        </table>
                    </td>
                    <td width="15"></td>
                    <td align="center">
                        <table width="5" border="0" align="center" cellpadding="0" cellspacing="0">
                        <tr>
                            <td width="5" height="5" bgcolor="#a8803a" style="border-radius:10px;"></td>
                        </tr>
                        </table>
                    </td>
                    <td width="15"></td>
                    <td align="center">
                        <table width="5" border="0" align="center" cellpadding="0" cellspacing="0">
                        <tr>
                            <td width="5" height="5" bgcolor="#a8803a" style="border-radius:10px;"></td>
                        </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
@endsection

{{-- main_content --}}
@section('main_content')
    <?php 
        $CompanyProfile = DB::table('company_profiles')->first();
        $company_name = $CompanyProfile->name;
    ?>
    <table border="0" cellpadding="0" cellspacing="0" style="width: 100%; height: 100%">
        <tr>
            <td>
                <div style="padding: 10px 40px;">
                  <table border="0" cellspacing="0" cellpadding="0">
                       <tr>
                            <td class="col_wid">
                                <form action="{{url('/')}}/api/user_comment_new" method="post" >

                                    <img src="" id="signupLogo"/>

                                  <div class="inputDiv">

                                    <input type="hidden" value="{{$data_array['user_id']}}" name="uid">
                                    <input type="hidden" value="{{$data_array['digisigner_doc_id']}}" name="digisigner_doc_id">
                                  </div>
                                <div class="from-group">
                                  <label class="inputLabel" for="password">Request Change :</label>
                                  <textarea class="form-control" name="comment" id="request_change" rows="3" cols="100"></textarea>
                                </div>
                                <div class="from-group" style="position: relative; top: 27px; color: white;border-radius: 5px; padding: 5px;">
                                    <input type="submit" name="submit"  value="Submit" id="submitButton" class="submitButton">
                                </div>

                                </form>
                              </td>
                          </tr>
                  </table>
                </div>
                <div style="padding: 40px 0px;">
                    <p style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';margin-bottom:24px; margin-top:0px;color: #767373;
                    font-size: 18px;font-weight: 500;margin-left: 42px;">Best regards,
                    </p>
                    <p style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';margin-bottom:10px; margin-top:10px;color: #767373;
                    font-size: 16px;font-weight: 400; margin-left: 42px;">{{$company_name}}
                    </p>
                </div>           
            </td>
        </tr>
    </table>
@endsection