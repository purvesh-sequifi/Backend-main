<?php 
    $name = $mailData['name'];
    $days = $mailData['days'];
?>

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        p {
            margin: .35em;
        }

        body {
            margin: 0;
        }

        @media only screen and (max-width: 600px) {
            .table-mainParent {
                width: 100% !important;
            }
        }
    </style>
</head>
<div style="background-color: #f2f2f2;height: 100%;">
    <div class="" style=" height: auto; max-width: 650px; margin: 0px auto;">
        <div class="aHl"></div>
        <div tabindex="-1"></div>
        <div class="ii gt">
            <div class="a3s aiL ">
                <table cellpadding="0" cellspacing="0" width="100%" style="table-layout: fixed;">
                    <tr>
                        <td>
                            <div align="center" style="padding: 15px; align-items: center; padding-top: 30px;">
                                <table cellpadding="0" cellspacing="0" width="100%" class="wrapper"
                                    style="background-color: #fff; border-radius: 10px;">
                                    <tr>
                                        <td>
                                            <table border="0" cellpadding="0" cellspacing="0" width="100%"
                                                style=" border-radius: 10px;">
                                                <tr>
                                                    <td bgcolor="#FFFFFF" align="left" style=" border-radius: 10px;">
                                                        <table border="0" cellpadding="0" cellspacing="0"
                                                            style="width: 100%; height: 100%; border-radius: 10px;">
                                                            <tr>
                                                                <td>
                                                                    <p
                                                                        style="margin-top: 10px; margin-bottom: 5px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';padding: 5px 20px; color: #333333; font-weight: 400; font-size: 14px; text-align: left; line-height: 24px;">
                                                                        <strong>Dear Admin,</strong>
                                                                    </p>

                                                                    <p
                                                                        style="margin-top: 0px; margin-bottom: 5px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';padding: 5px 20px; color: #333333; font-weight: 400; font-size: 14px; text-align: left; line-height: 24px;">
                                                                        {{$name}} has now completed 150 days in the system. Please review their status to determine if further action is required.</p>

                                                                    <p
                                                                        style="margin-top: 20px; margin-bottom: 0px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';padding: 5px 20px; color: #333333; font-weight: 400; font-size: 14px; text-align: left; line-height: 24px;">
                                                                        Next Steps:</p>
                                                                    <p
                                                                        style="margin-top: 0px; margin-bottom: 0px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';padding: 5px 20px; color: #333333; font-weight: 400; font-size: 14px; text-align: left; line-height: 24px;">
                                                                        - Confirm their current role and location.</p>
                                                                    <p
                                                                        style="margin-top: 0px; margin-bottom: 5px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';padding: 0px 20px; color: #333333; font-weight: 400; font-size: 14px; text-align: left; line-height: 24px;">
                                                                        - Update their records if needed.</p>
                                                                    <p
                                                                        style="margin-top: 30px; margin-bottom: 5px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';padding: 5px 20px; color: #333333; font-weight: 400; font-size: 14px; text-align: left; line-height: 24px;">
                                                                        </p>

                                                                   
                                                                    <p
                                                                        style="margin-top: 0px; margin-bottom: 10px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';padding: 5px 20px; color: #333333; font-weight: 400; font-size: 14px; text-align: left; line-height: 24px;">
                                                                        Best regards,<br>Sequifi</p>
                                                                </td>
                                                            </tr>
                                                        </table>
                                                    </td>
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</div>