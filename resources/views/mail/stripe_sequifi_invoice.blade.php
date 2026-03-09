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
<?php
$stripelg_img = config('app.aws_s3bucket_url') . '/public_images/stripelg.png';
$sequifi_img = config('app.aws_s3bucket_url') . '/public_images/sequifi.png';
$rightimgd_img = config('app.aws_s3bucket_url') . '/images/rightimgd.png';
$dwar_img = config('app.aws_s3bucket_url') . '/images/dw-ar.png';
?>
<div style="background-color: #525f7f;">
    <div class="" style=" height: auto; max-width: 650px; margin: 0px auto;">
        <div class="aHl"></div>
        <div tabindex="-1"></div>
        <div class="ii gt">
            <div class="a3s aiL ">
                <table cellpadding="0" cellspacing="0" width="100%" style="table-layout: fixed;">
                    <tr>
                        <td>
                            <div align="center" style="padding: 15px; align-items: center;">
                                <table cellpadding="0" cellspacing="0" width="100%" class="wrapper"
                                    style="margin-bottom: 15px;">
                                    <tr>
                                        <td style="padding: 0px;width: 40px;">
                                            <div style="background-color: #fff; border-radius: 100%;">
                                                <img src="{{ $sequifi_img }}" alt=""
                                                    style="width: 40px;height: 40px; min-width: 40px;min-height: 40px;">
                                            </div>
                                        </td>
                                        <td
                                            style="margin-top: 10px; margin-bottom: 5px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';padding: 5px 0px; color: #fff; font-weight: 600; font-size: 14px; text-align: left; line-height: 24px;padding-left: 10px;">
                                            Sequifi Inc.
                                        </td>
                                    </tr>
                                </table>

                                <table cellpadding="0" cellspacing="0" width="100%" class="wrapper"
                                    style="background-color: #fff; border-radius: 10px;padding: 10px;margin-top: 20px;">
                                    <tr>
                                        <td>
                                            <table border="0" cellpadding="0" cellspacing="0" width="100%"
                                                style=" border-radius: 10px;">
                                                <tr>
                                                    <td align="left">
                                                        <div style="border-radius: 12px;
                                                        background-color: #FFFFFF;padding: 10px;">
                                                            <table border="0" cellpadding="0" cellspacing="0"
                                                                style="width: 100%; height: 100%; border-radius: 10px;">
                                                                <tr>
                                                                    <td>
                                                                        <table style="width: 100%;">
                                                                            <tr>
                                                                                <td
                                                                                    style="border-bottom: 1px solid #eee; padding-bottom: 10px;">
                                                                                    <p
                                                                                        style="margin-top: 0px; margin-bottom: 5px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol'; color: rgb(122,122,122); font-weight: 600; font-size: 14px; text-align: left; line-height: 24px;">
                                                                                        Invoice from Sequifi Inc.
                                                                                    </p>
                                                                                    <h3
                                                                                        style="margin-top: 12px; margin-bottom: 5px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol'; color: #333; font-weight: 600; font-size: 30px; text-align: left; line-height: 24px;">
                                                                                        ${{ $invoice['amount_due']/100 }}</h3>
                                                                                        
                                                                                    <p
                                                                                        style="margin-top: 12px; margin-bottom: 5px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol'; color: rgb(122,122,122); font-weight: 600; font-size: 14px; text-align: left; line-height: 24px;">
                                                                                        Due {{ date('M d, Y', strtotime($invoice['due_date'])) }}
                                                                                        
                                                                                    </p>
                                                                                </td>
                                                                                <td
                                                                                    style="text-align: right; width: 100px;">
                                                                                    <img src="{{ $rightimgd_img }}" alt=""
                                                                                        style="width: 100px;">
                                                                                </td>
                                                                            </tr>
                                                                        </table>
                                                                        <p
                                                                            style="margin-top: 10px; margin-bottom: 5px;">
                                                                            <a href="{{ $invoice['invoice_pdf'] }}"
                                                                                style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol'; color: rgb(122,122,122); font-weight: 600; font-size: 14px; text-align: left; line-height: 24px;text-decoration: none;"><img
                                                                                    src="{{ $dwar_img }}" alt="" width="12"
                                                                                    style="padding-right: 5px;">
                                                                                Download invoice</a>
                                                                        </p>
                                                                        <p
                                                                            style="margin-top: 12px; margin-bottom: 5px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol'; color: #333; font-weight: 400; font-size: 14px; text-align: left; line-height: 24px;">
                                                                            <strong
                                                                                style="font-weight: 300;padding-right: 40px;color: rgb(122,122,122);">To</strong>
                                                                                {{ $invoice['customer_name'] }}
                                                                        </p>
                                                                        
                                                                        <p
                                                                            style="margin-top: 12px; margin-bottom: 5px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol'; color: #333; font-weight: 400; font-size: 14px; text-align: left; line-height: 24px;">
                                                                            <strong
                                                                                style="font-weight: 300;padding-right: 25px;color: rgb(122,122,122);">From</strong>
                                                                            {{ $invoice['account_name'] }}
                                                                        </p>

                                                                        <div style="margin-top: 20px;">
                                                                            <a href="{{env('FRONTEND_BASE_URL')}}/pay-invoice/{{ $invoice_no}}" target="_blank"
                                                                                style="background-color: #5c78fc; color: #fff; text-decoration: none;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol'; text-align: center; font-weight: 500; font-size: 16px; padding: 15px; display: block;border-radius: 10px;">
                                                                                Schedule or pay now</a>
                                                                        </div>
                                                                    </td>
                                                                </tr>
                                                            </table>
                                                        </div>
                                                    </td>

                                                    <td bgcolor="#FFFFFF" align="left"
                                                        style=" border-radius: 10px; display: none;">   
                                                    </td>
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>
                                </table>

                                <table cellpadding="0" cellspacing="0" width="100%" class="wrapper"
                                    style="background-color: #fff; border-radius: 10px;padding: 10px;margin-top: 20px;">
                                    <tr>
                                        <td>
                                            <table border="0" cellpadding="0" cellspacing="0" width="100%"
                                                style=" border-radius: 10px;">
                                                <tr>
                                                    <td align="left">
                                                        <div style="border-radius: 12px;
                                                        background-color: #FFFFFF;padding: 10px;">
                                                            <table border="0" cellpadding="0" cellspacing="0"
                                                                style="width: 100%; height: 100%; border-radius: 10px;">
                                                                <tr>
                                                                    <td>
                                                                        <table style="width: 100%;">
                                                                            <tr>
                                                                                <td>
                                                                                    <p
                                                                                        style="margin-top: 0px; margin-bottom: 5px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol'; color: #333; font-weight: 600; font-size: 14px; text-align: left; line-height: 24px;">
                                                                                        Invoice #{{ $invoice['number'] }}
                                                                                    </p>
                                                                                    <table
                                                                                        style="width: 100%;margin-top: 20px;">
                                                                                        <tr>
                                                                                            <td
                                                                                                style="border-bottom: 1px solid #eee; padding-bottom: 8px;">
                                                                                                <p
                                                                                                    style="margin-top: 0px; margin-bottom: 5px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol'; color: #333; font-weight: 600; font-size: 14px; text-align: left; line-height: 24px;">
                                                                                                    {{ $invoice['description'] }}</p>
                                                                                                <p
                                                                                                    style="margin-top: 0px; margin-bottom: 5px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol'; color: rgb(122,122,122); font-weight: 600; font-size: 14px; text-align: left; line-height: 24px;">
                                                                                                    Qty {{ $invoice['lines']['total_count'] }}</p>
                                                                                            </td>
                                                                                            <td
                                                                                                style="border-bottom: 1px solid #eee; padding-bottom: 8px;">
                                                                                                <p
                                                                                                    style="margin-top: 0px; margin-bottom: 5px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol'; color: #333; font-weight: 600; font-size: 14px; text-align: right; line-height: 24px;">
                                                                                                    ${{ $invoice['amount_due']/100 }}</p>
                                                                                                <p
                                                                                                    style="margin-top: 0px; margin-bottom: 5px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol'; color: rgb(122,122,122); font-weight: 600; font-size: 14px; text-align: right; line-height: 24px;">
                                                                                                    </p>
                                                                                            </td>
                                                                                        </tr>
                                                                                        <tr>
                                                                                            <td
                                                                                                style="border-bottom: 1px solid #eee; padding-bottom: 8px;">
                                                                                                <p
                                                                                                    style="margin-top: 0px; margin-bottom: 5px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol'; color: #333; font-weight: 600; font-size: 14px; text-align: left; line-height: 24px;">
                                                                                                    Total</p>
                                                                                            </td>
                                                                                            <td
                                                                                                style="border-bottom: 1px solid #eee; padding-bottom: 8px;padding-top: 10px;">
                                                                                                <p
                                                                                                    style="margin-top: 0px; margin-bottom: 5px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol'; color: #333; font-weight: 600; font-size: 14px; text-align: right; line-height: 24px;">
                                                                                                    ${{ $invoice['amount_due']/100 }}</p>
                                                                                            </td>
                                                                                        </tr>
                                                                                    </table>
                                                                                </td>
                                                                            </tr>
                                                                        </table>
                                                                        <p
                                                                            style="margin-top: 10px; margin-bottom: 5px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol'; color: rgb(122,122,122); font-weight: 600; font-size: 14px; line-height: 24px;">
                                                                            Questions? Contact us at <span
                                                                                style="color: #5c78fc;">r.kumar@sequifi.com</span>
                                                                            or call us at <span
                                                                                style="color: #5c78fc;">+1
                                                                                307-761-1135</span>.
                                                                        </p>
                                                                    </td>
                                                                </tr>
                                                            </table>
                                                        </div>
                                                    </td>

                                                    <td bgcolor="#FFFFFF" align="left"
                                                        style=" border-radius: 10px; display: none;">
                                                    </td>
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>
                                </table>

                                <table cellpadding="0" cellspacing="0" width="100%" class="wrapper"
                                    style="background-color: #fff; border-radius: 10px;padding: 10px;margin-top: 20px;">
                                    <tr>
                                        <td>
                                            <table border="0" cellpadding="0" cellspacing="0" width="100%"
                                                style=" border-radius: 10px;">
                                                <tr>
                                                    <td align="left">
                                                        <div style="border-radius: 12px;
                                                        background-color: #FFFFFF;padding: 10px;">
                                                            <table border="0" cellpadding="0" cellspacing="0"
                                                                style="width: 100%; height: 100%; border-radius: 10px;">
                                                                <tr>
                                                                    <td>
                                                                        <table style="width: 100%;">
                                                                            <tr>
                                                                                <td>
                                                                                    <p
                                                                                        style="margin-top: 0px; margin-bottom: 5px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol'; color: rgb(122,122,122); font-weight: 600; font-size: 14px; text-align: left; line-height: 24px;">
                                                                                        PAY {{ $invoice['amount_due']/100 }} WITH A BANK TRANSFER
                                                                                    </p>
                                                                                    <p
                                                                                        style="margin-top: 0px; margin-bottom: 5px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol'; color: rgb(122,122,122); font-weight: 300; font-size: 14px; text-align: left; line-height: 24px;">
                                                                                        Bank transfers can take up to
                                                                                        two business days. To pay via
                                                                                        bank transfer, transfer funds
                                                                                        using the following bank
                                                                                        information.
                                                                                    </p>

                                                                                    <div
                                                                                        style="background-color: rgba(0, 0, 0, 0.03);
                                                                                    border-radius: 6px;
                                                                                    border: 1px solid rgba(0, 0, 0, 0.08); padding: 12px;">
                                                                                        <table
                                                                                            style="width: 100%;">
                                                                                            <tr>
                                                                                                <td>
                                                                                                    <p
                                                                                                        style="margin-top: 0px; margin-bottom: 5px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol'; color: rgb(122,122,122); font-weight: 500; font-size: 14px; text-align: left; line-height: 24px;">
                                                                                                        Account holder</p>
                                                                                                </td>
                                                                                                <td>
                                                                                                    <p
                                                                                                        style="margin-top: 0px; margin-bottom: 5px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol'; color: #333; font-weight: 600; font-size: 14px; text-align: left; line-height: 24px;">
                                                                                                        Sequifi Inc.</p>
                                                                                                </td>
                                                                                            </tr>
                                                                                            <tr>
                                                                                                <td>
                                                                                                    <p
                                                                                                        style="margin-top: 0px; margin-bottom: 5px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol'; color: rgb(122,122,122); font-weight: 500; font-size: 14px; text-align: left; line-height: 24px;">
                                                                                                        Routing number</p>
                                                                                                </td>
                                                                                                <td>
                                                                                                    <p
                                                                                                        style="margin-top: 0px; margin-bottom: 5px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol'; color: #333; font-weight: 600; font-size: 14px; text-align: left; line-height: 24px;">
                                                                                                        999999999</p>
                                                                                                </td>
                                                                                            </tr>
                                                                                            <tr>
                                                                                                <td>
                                                                                                    <p
                                                                                                        style="margin-top: 0px; margin-bottom: 5px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol'; color: rgb(122,122,122); font-weight: 500; font-size: 14px; text-align: left; line-height: 24px;">
                                                                                                        Account number</p>
                                                                                                </td>
                                                                                                <td>
                                                                                                    <p
                                                                                                        style="margin-top: 0px; margin-bottom: 5px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol'; color: #333; font-weight: 600; font-size: 14px; text-align: left; line-height: 24px;">
                                                                                                        11119964891778262</p>
                                                                                                </td>
                                                                                            </tr>
                                                                                            <tr>
                                                                                                <td>
                                                                                                    <p
                                                                                                        style="margin-top: 0px; margin-bottom: 5px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol'; color: rgb(122,122,122); font-weight: 500; font-size: 14px; text-align: left; line-height: 24px;">
                                                                                                        SWIFT code</p>
                                                                                                </td>
                                                                                                <td>
                                                                                                    <p
                                                                                                        style="margin-top: 0px; margin-bottom: 5px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol'; color: #333; font-weight: 600; font-size: 14px; text-align: left; line-height: 24px;">
                                                                                                        TESTUS99XXX</p>
                                                                                                </td>
                                                                                            </tr>
                                                                                            <tr>
                                                                                                <td>
                                                                                                    <p
                                                                                                        style="margin-top: 0px; margin-bottom: 5px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol'; color: rgb(122,122,122); font-weight: 500; font-size: 14px; text-align: left; line-height: 24px;">
                                                                                                        Reference</p>
                                                                                                </td>
                                                                                                <td>
                                                                                                    <p
                                                                                                        style="margin-top: 0px; margin-bottom: 5px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol'; color: #333; font-weight: 600; font-size: 14px; text-align: left; line-height: 24px;">
                                                                                                        5526A08F-0004</p>
                                                                                                </td>
                                                                                            </tr>
                                                                                            <tr>
                                                                                                <td>
                                                                                                    <p
                                                                                                        style="margin-top: 0px; margin-bottom: 5px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol'; color: rgb(122,122,122); font-weight: 500; font-size: 14px; text-align: left; line-height: 24px;">
                                                                                                        Bank name</p>
                                                                                                </td>
                                                                                                <td>
                                                                                                    <p
                                                                                                        style="margin-top: 0px; margin-bottom: 5px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol'; color: #333; font-weight: 600; font-size: 14px; text-align: left; line-height: 24px;">
                                                                                                        US Test Bank</p>
                                                                                                </td>
                                                                                            </tr>
                                                                                            <tr>
                                                                                                <td>
                                                                                                    <p
                                                                                                        style="margin-top: 0px; margin-bottom: 5px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol'; color: rgb(122,122,122); font-weight: 500; font-size: 14px; text-align: left; line-height: 24px;">
                                                                                                        Account type</p>
                                                                                                </td>
                                                                                                <td>
                                                                                                    <p
                                                                                                        style="margin-top: 0px; margin-bottom: 5px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol'; color: #333; font-weight: 600; font-size: 14px; text-align: left; line-height: 24px;">
                                                                                                        Checking</p>
                                                                                                </td>
                                                                                            </tr>
                                                                                            <tr>
                                                                                                <td colspan="2">
                                                                                                    <p
                                                                                                        style="margin-top: 0px; margin-bottom: 5px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol'; color: rgb(122,122,122); font-weight: 600; font-size: 16px; text-align: left; line-height: 24px;">
                                                                                                        Addresses</p>
                                                                                                </td>
                                                                                            </tr>
                                                                                            <tr>
                                                                                                <td>
                                                                                                    <p
                                                                                                        style="margin-top: 0px; margin-bottom: 5px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol'; color: rgb(122,122,122); font-weight: 400; font-size: 14px; text-align: left; line-height: 24px;">
                                                                                                        Bank</p>
                                                                                                </td>
                                                                                                <td>
                                                                                                    <p
                                                                                                        style="margin-top: 0px; margin-bottom: 5px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol'; color: #333; font-weight: 600; font-size: 14px; text-align: left; line-height: 24px;">
                                                                                                        420 Montgomery Street San Francisco, California 94104 United States</p>
                                                                                                </td>
                                                                                            </tr>
                                                                                            <tr>
                                                                                                <td>
                                                                                                    <p
                                                                                                        style="margin-top: 0px; margin-bottom: 5px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol'; color: rgb(122,122,122); font-weight: 400; font-size: 14px; text-align: left; line-height: 24px;">
                                                                                                        Account holder</p>
                                                                                                </td>
                                                                                                <td>
                                                                                                    <p
                                                                                                        style="margin-top: 0px; margin-bottom: 5px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol'; color: #333; font-weight: 600; font-size: 14px; text-align: left; line-height: 24px;">510 Townsend Street San Francisco, California 94103 United States</p>
                                                                                                </td>
                                                                                            </tr>
                                                                                        </table>
                                                                                    </div>

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
                                        </td>
                                    </tr>
                                </table>


                                <table border="0" cellpadding="0" cellspacing="0" style="width: 100%; height: 100%">
                                    <tr>
                                        <td style="padding: 0px 15px;">


                                            <p
                                                style="margin-top: 15px; margin-bottom: 5px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';padding: 5px 20px; color: #fff; font-weight: 400; font-size: 14px; text-align: center;opacity: 0.4;">
                                                Powered by <img src="{{ $stripelg_img }}" alt="" height="23" style="margin-bottom: -6px;">   |   Learn more about Stripe Invoicing</p>
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