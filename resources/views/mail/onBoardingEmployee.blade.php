@extends('layout.mail_layout')

@section('title') 
    EOD Report For OnBoarding
@endsection

{{-- Top head in mail --}}
@section('top_head') 
    <tr>
        <td bgcolor="#ffffff" align="left">
            <div style="padding: 15px 40px;">
                <p style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';color: #767373;
                    font-size: 30px;font-weight: 500; text-align: center;">
                    EOD Report For OnBoarding!
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
                                <p class="customer_detail col_wid"><strong>Id </strong></p>
                            </td>
                            <td class="customer_detail col_wid">
                                <p class="customer_detail col_wid"><strong>Emp Name  </strong></p>
                            </td>

                            <td>
                                <p class="customer_detail col_wid"><strong>Emp Email </strong></p>
                            </td>

                            <td>
                                <p class="customer_detail col_wid"><strong>Mobile No.</strong></p>
                            </td>
                            <td>
                                <p class="customer_detail col_wid"><strong>Created_at</strong></p>
                            </td>

                            <td>
                                <p class="customer_detail col_wid"><strong>updated_at</strong></p>
                            </td>
                        </tr>
                          @foreach($data as $val)
                            <tr>
                              <td>
                                <p class="customer_detail" ><strong>{{isset($val->id)?$val->id:'null'}}</strong></p>
                              </td>

                              <td class="product_name">
                                {{isset($val->first_name)?$val->first_name:'null'}} {{isset($val->last_name)?$val->last_name:'null'}}
                              </td>

                              <td class="product_name">
                                {{isset($val->email)?$val->email:'null'}}
                              </td>

                                <td class="product_name">
                                    {{isset($val->mobile_no)?$val->mobile_no:'null'}}
                                </td>

                                <td class="product_name">
                                    {{isset($val->created_at)?$val->created_at:'null'}}
                                </td>

                                <td class="product_name">
                                    {{isset($val->updated_at)?$val->updated_at:'null'}}
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