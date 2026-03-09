@extends('layout.mail_layout')

@section('title') 
    User Profile Update
@endsection

{{-- Top head in mail --}}
@section('top_head') 
    <tr>
        <td bgcolor="#ffffff" align="left">
            <div style="padding: 15px 40px;">
                <p style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';color: #767373;
                    font-size: 30px;font-weight: 500; text-align: center;">
                    User Profile Update!
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
                            <p style="padding: 0px 5px;" class="customer_detail col_wid"><strong> Employee Id</strong></p>
                        </td>
                        <td class="customer_detail col_wid">
                            <p style="padding: 0px 5px;" class="customer_detail col_wid"><strong> First Name  </strong></p>
                        </td>

                        <td>
                            <p style="padding: 0px 5px;" class="customer_detail col_wid"><strong> Middle Name </strong></p>
                        </td>
                        
                        <td>
                            <p style="padding: 0px 5px;" class="customer_detail col_wid"><strong> Last Name </strong></p>
                        </td>
                        
                        <td>
                            <p style="padding: 0px 5px;" class="customer_detail col_wid"><strong> Sex </strong></p>
                        </td>

                        <td>
                            <p style="padding: 0px 5px;" class="customer_detail col_wid"><strong> DOB </strong></p>
                        </td>
                        
                        <td>
                            <p style="padding: 0px 5px;" class="customer_detail col_wid"><strong> Mobile no </strong></p>
                        </td>
                        
                        <td>
                            <p style="padding: 0px 5px;" class="customer_detail col_wid"><strong> Email </strong></p>
                        </td>
                    </tr>
                      
               
                      <tr>
                        <td>
                          <p style="padding: 0px 5px;" class="customer_detail" ><strong>{{isset($check->employee_id)?$check->employee_id :'  -'}}</strong></p>
                        </td>

                        <td class="product_name">
                            <p style="padding: 0px 5px;">
                                {{isset($check->first_name)?$check->first_name:'  -'}} 
                            </p>
                        </td>
                        <td class="product_name">
                            <p style="padding: 0px 5px;">
                                {{isset($check->middle_name)?$check->middle_name:'  -'}}
                            </p>
                         </td>
                        <td class="product_name">
                            <p style="padding: 0px 5px;">
                                {{isset($check->last_name)?$check->last_name:'  -'}}
                            </p>
                         </td>


                          <td class="product_name">
                            <p style="padding: 0px 5px;">
                                {{isset($check->sex)?$check->sex:'  -'}}
                            </p>
                          </td>

                          <td class="product_name">
                            <p style="padding: 0px 5px;">
                                {{isset($check->dob) && $check->dob != '0000-00-00'?date('m-d-Y',strtoTime($check->dob)):'  -'}}
                            </p>
                          </td>

                          <td class="product_name">
                            <p style="padding: 0px 5px;">
                                {{isset($check->mobile_no)?$check->mobile_no:'  -'}}
                            </p>
                          </td>

                          <td class="product_name">
                            <p style="padding: 0px 5px;">
                                {{isset($check->email)?$check->email:'  -'}}
                            </p>
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