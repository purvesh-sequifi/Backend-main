@extends('layout.mail_layout')

@section('title') 
    Setting Setup
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
                    Setting Setup
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
                   <table border="1" cellspacing="0" cellpadding="0">
                       <tr>
                            <td class="col_wid">
                                <p class="customer_detail col_wid"><strong></strong></p>
                            </td>
                            <td class="customer_detail col_wid">
                                <p class="customer_detail col_wid"><strong>Period  </strong></p>
                            </td>

                            <td>
                                <p class="customer_detail col_wid"><strong>Pay Date </strong></p>
                            </td>

                        </tr>
                          @foreach($data as $val)
                            <tr>
                              <td>
                                <p class="customer_detail" ><strong>Recon {{isset($val->id)?$val->id:'null'}}</strong></p>
                              </td>

                              <td class="product_name">
                                {{isset($val->period_from)?$val->period_from:'null'}} - {{isset($val->period_to)?$val->period_to:'null'}}
                              </td>

                              <td class="product_name">
                                {{isset($val->day_date)?$val->day_date:'null'}}
                              </td>

                            </tr>
                          @endforeach
                         <tr>
                            <td>To</td>
                         </tr>
                          @foreach($check as $vals)
                          <tr>
                            <td>
                              <p class="customer_detail" ><strong>Recon {{isset($vals->id)?$vals->id:'null'}}</strong></p>
                            </td>

                            <td class="product_name">
                              {{isset($vals->period_from)?$vals->period_from:'null'}} - {{isset($vals->period_to)?$vals->period_to:'null'}}
                            </td>

                            <td class="product_name">
                                {{isset($val->day_date)?$val->day_date:'null'}}
                              </td>
                          </tr>
                        @endforeach
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