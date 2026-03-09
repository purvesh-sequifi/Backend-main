@extends('layout.mail_layout')

@section('title') 
    Reset Password
@endsection

{{-- Top head in mail --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.5.1/dist/jquery.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.5.1/dist/jquery.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.min.js"></script>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/2.1.1/jquery.min.js"></script>
    <link href="https://stackpath.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css"
        rel="stylesheet" type="text/css" />
@section('top_head')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <tr>
<style type="text/css">
    body {
        margin: 0px;
    }

    #toggle_npwd
    {
        cursor: pointer;
        margin: 10px;
        position: absolute;
        right: 17px;
    }
    #toggle_cpwd
    {
        cursor: pointer;
        margin: 10px;
        position: absolute;
        right: 17px;
    }
    .eyestyle {
        display: flex;
        position: relative;
    }
    #cpassword-error {
        position: absolute;
        top: 39px;
        color: brown;
    }
    #npassword-error {
        position: absolute;
        top: 39px;
        color: brown;
    }
</style>
    <tr>
        <td bgcolor="#ffffff" align="left">
            <div style="padding: 15px 40px;">
                <p style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';color: #767373;
                    font-size: 30px;font-weight: 500; text-align: center;">
                    Reset Password
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
                   <table border="0" cellspacing="0" cellpadding="0"style="width: 80%;margin: auto;">
                       <tr>
                            <td class="col_wid">
                                <form id="resetpassword" action="{{url('/')}}/api/update-Password" method="post" >
                                    <div class="form-group mt-4">
                                        <div class="row">
                                            <input type="hidden" value="{{$id}}" name="uid">
                                            <div class="col-md-4">
                                                <label for="npassword">New Password:</label>
                                            </div>
                                            <div class="col-md-8 eyestyle">
                                                <input type="password" name="password" class="form-control" id="npassword" placeholder="********" required>
                                                <span id="toggle_npwd" class="fa fa-fw fa-eye field_icon"></span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="form-group mt-4">
                                        <div class="row">
                                            <div class="col-md-4">
                                                <label for="npassword">Confirm Password:</label>
                                            </div>
                                            <div class="col-md-8 eyestyle">
                                                <input type="password" class="form-control" name="confirmPassword" id="cpassword" placeholder="********" required>
                                                <span id="toggle_cpwd" class="fa fa-fw fa-eye field_icon"></span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="form-group mt-4">
                                        <div class="row">
                                            <div class="col-md-12" style="text-align:center;margin-left: 85px">
                                                <input type="submit" name="submit"  value="Continue" id="submitButton" class="submitButton pure-button pure-button-primary" style="background:#0225ee;color:#fff;padding:6px;text-decoration:none;border-radius:5px">
                                            </div>
                                        </div>
                                    </div>
                                </form>
                              </td>
                          </tr>
                  </table>
                </div>   
            </td>
        </tr>
    </table>
@endsection
