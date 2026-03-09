<!DOCTYPE html>
<html lang="en">
<style>
    @media screen and (max-width: 490px) {
        .heading_cls {
            font-size: 16px !important;
            font-weight: 600 !important;
            top: 44% !important;
        }
    }
</style>
<?php 
$CompanyProfile = DB::table('company_profiles')->first();
$company_and_other_static_images = \App\Models\SequiDocsEmailSettings::company_and_other_static_images($CompanyProfile);
$header_image = $company_and_other_static_images['header_image'];
$Company_Logo = $company_and_other_static_images['Company_Logo'];
?>
<body>
    <div style="max-width: 800px; margin: 0px auto; padding: 20px;padding-top: 40px;background-color: #fff;">
        <div style="display: flex;justify-content: space-between; position: relative;">
            <img src="{{ asset('pdf-images/header-img.png') }}" alt="" style="width: 100%;
        height: 120px;">
            <div style="border-radius: 25px;
        background-color: #fff;
        width: 160px;
        height: 155px;
        position: absolute;
        top: -17px;
        left: 30px;
        box-shadow: 0px 2px 19px 0px #305f9736;
        display: flex;
        justify-content: center;
        align-items: center;"><img src="{{ $Company_Logo }}" alt="" style="width: 100%;"></div>
            <h3 class="heading_cls" style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol';
            position: absolute;top: 35%;right: 5%;color: #ffffff;font-size: 24px;font-weight: 500; margin: 0px;">S-
                Clearance
                Report </h3>
        </div>

        <div style="margin-top: 60px;box-shadow: 0px 0px 10px 0px #30303014;border-radius: 10px;padding: 20px;">
            <h3
                style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol';font-size: 20px;font-weight: 500;margin: 0px;color: #212121;">
                Record Summary</h3>

            <div style="border-top: 1px solid #F5F5F5; width: 100%; margin-top: 20px; margin-bottom: 20px;"></div>

            <div style="display: flex; justify-content: space-between; flex-wrap: wrap;
            gap: 20px;">
                <div style="display: flex; align-items: center;">
                    <div style="width: 120px; height: 120px;"><img src="{{ asset('pdf-images/user-placeholder.png') }}" alt="" width="100%"></div>
                    <div style="margin-left: 20px;">
                        <h4
                            style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol';font-size: 16px;font-weight: 500;margin: 0px;color: #212121;">
                            Chip Portsmith</h4>
                        <p
                            style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol';font-size: 13px;font-weight: 400;margin: 0px;color: #000000;margin-top: 8px;">
                            Age 68</p>
                        <p
                            style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol';font-size: 13px;font-weight: 400;margin: 0px;color: #000000;margin-top: 8px;line-height: 20px;">
                            100 E Bills Ave <br />
                            Hampton, AR 29672 <br />
                            USA</p>
                    </div>
                </div>

                <div>
                    <div style="display: flex; gap: 40px; justify-content: space-between; flex-wrap: wrap;">
                        <div style="display: inline-block;min-width: 140px; text-align: center;">
                            <span
                                style="width: 40px;height: 40px;display: flex;background-color: #6378F7;border-radius: 50%;justify-content: center;align-items: center;color: #ffffff;font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 14px; font-weight: 500; margin: 0px auto;">1</span>
                            <p
                                style="margin: 0px;margin: 0px;font-size: 12px;color: #616161;font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; margin-top: 10px">
                                Criminal Record </p>
                        </div>

                        <div style="display: inline-block;min-width: 140px; text-align: center;">
                            <span
                                style="width: 40px;height: 40px;display: flex;background-color: #6378F7;border-radius: 50%;justify-content: center;align-items: center;color: #ffffff;font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 14px; font-weight: 500; margin: 0px auto;">0</span>
                            <p
                                style="margin: 0px;margin: 0px;font-size: 12px;color: #616161;font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; margin-top: 10px">
                                Most Wanted List </p>
                        </div>
                    </div>

                    <div
                        style="display: flex; gap: 40px; justify-content: space-between; margin-top: 25px; flex-wrap: wrap;">
                        <div style="display: inline-block;min-width: 140px; text-align: center;">
                            <span
                                style="width: 40px;height: 40px;display: flex;background-color: #6378F7;border-radius: 50%;justify-content: center;align-items: center;color: #ffffff;font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 14px; font-weight: 500; margin: 0px auto;">2</span>
                            <p
                                style="margin: 0px;margin: 0px;font-size: 12px;color: #616161;font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; margin-top: 10px">
                                National Sex Offender </p>
                        </div>

                        <div style="display: inline-block;min-width: 140px; text-align: center;">
                            <span
                                style="width: 40px;height: 40px;display: flex;background-color: #6378F7;border-radius: 50%;justify-content: center;align-items: center;color: #ffffff;font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 14px; font-weight: 500; margin: 0px auto;">0</span>
                            <p
                                style="margin: 0px;margin: 0px;font-size: 12px;color: #616161;font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; margin-top: 10px">
                                Potential OFAC Match </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div style="margin-top: 40px;box-shadow: 0px 0px 10px 0px #30303014;border-radius: 10px;padding: 20px;">
            <h3
                style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol';font-size: 20px;font-weight: 500;margin: 0px;color: #212121;">
                Detailed Criminal Records</h3>

            <div style="border-top: 1px solid #F5F5F5; width: 100%; margin-top: 20px; margin-bottom: 20px;"></div>

            <div style="display: flex; justify-content: space-between; flex-wrap: wrap;
            gap: 20px;">
                <div style="">
                    <h4
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol';font-size: 18px;font-weight: 400;margin: 0px;color: #6078EC;">
                        Applicant Data as Entered </h4>
                    <h4
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol';font-size: 16px;font-weight: 500;margin: 0px;color: #212121; margin-top: 20px;">
                        Chip Portsmith </h4>
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol';font-size: 13px;font-weight: 400;margin: 0px;color: #000000;margin-top: 8px; margin-top: 15px;">
                        <strong style="font-weight: 500;">Record State:</strong> AZ
                    </p>
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol';font-size: 13px;font-weight: 400;margin: 0px;color: #000000;margin-top: 8px;">
                        <strong style="font-weight: 500;">Age:</strong> 83
                    </p>
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol';font-size: 13px;font-weight: 400;margin: 0px;color: #000000;margin-top: 8px;">
                        <strong style="font-weight: 500;">Date of Birth*:</strong> XX/XX/1941
                    </p>
                </div>

                <div style="">
                    <h4
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol';font-size: 18px;font-weight: 400;margin: 0px;color: #6078EC;">
                        Physical Features </h4>
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol';font-size: 12px;font-weight: 300;margin: 0px;color: #000000;margin-top: 8px; margin-top: 15px;">
                        Physical Details</p>

                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol';font-size: 13px;font-weight: 400;margin: 0px;color: #000000;margin-top: 8px;">
                        <strong style="font-weight: 500;">Record State:</strong> AZ
                    </p>
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol';font-size: 13px;font-weight: 400;margin: 0px;color: #000000;margin-top: 8px;">
                        <strong style="font-weight: 500;">Age:</strong> 83
                    </p>
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol';font-size: 13px;font-weight: 400;margin: 0px;color: #000000;margin-top: 8px;">
                        <strong style="font-weight: 500;">Date of Birth*:</strong> XX/XX/1941
                    </p>
                </div>
            </div>

            <div style="margin-top: 25px;">
                <h4
                    style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol';font-size: 18px;font-weight: 400;margin: 0px;color: #6078EC;">
                    Aliases</h4>

                <p
                    style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol';font-size: 13px;font-weight: 500;margin: 0px;color: #000000;margin-top: 8px;">
                    No aliases found</p>
            </div>

            <div style="border-top: 1px solid #F5F5F5; width: 100%; margin-top: 20px; margin-bottom: 20px;"></div>
            <div style="margin-top: 25px;">
                <h4
                    style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol';font-size: 18px;font-weight: 400;margin: 0px;color: #6078EC;">
                    Detailed Summary</h4>
                <div
                    style="display: flex; gap: 40px; margin-top: 20px; justify-content: space-between; flex-wrap: wrap;">
                    <div style="text-align: center; min-width: 150px; max-width: 150px;">
                        <p
                            style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 14px; color: #616161; font-weight: 500;">
                            Incident(s):</p>
                        <p
                            style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 16px; font-weight: 500; color: #212121;">
                            0</p>
                    </div>

                    <div style="text-align: center; min-width: 150px; max-width: 150px;">
                        <p
                            style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 14px; color: #616161; font-weight: 500;">
                            Booking(s):</p>
                        <p
                            style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 16px; font-weight: 500; color: #212121;">
                            0</p>
                    </div>

                    <div style="text-align: center; min-width: 150px; max-width: 150px;">
                        <p
                            style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 14px; color: #616161; font-weight: 500;">
                            Arrest(s):</p>
                        <p
                            style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 16px; font-weight: 500; color: #212121;">
                            0</p>
                    </div>
                </div>

                <div
                    style="display: flex; gap: 40px; margin-top: 20px; justify-content: space-between; flex-wrap: wrap;">
                    <div style="text-align: center; min-width: 150px; max-width: 150px;">
                        <p
                            style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 14px; color: #616161; font-weight: 500;">
                            Court Action(s):</p>
                        <p
                            style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 16px; font-weight: 500; color: #212121;">
                            1</p>
                    </div>

                    <div style="text-align: center; min-width: 150px; max-width: 150px;">
                        <p
                            style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 14px; color: #616161; font-weight: 500;">
                            Sentencing(s):</p>
                        <p
                            style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 16px; font-weight: 500; color: #212121;">
                            0</p>
                    </div>

                    <div style="text-align: center; min-width: 150px; max-width: 150px;">
                        <p
                            style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 14px; color: #616161; font-weight: 500;">
                            Supervision(s):</p>
                        <p
                            style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 16px; font-weight: 500; color: #212121;">
                            0</p>
                    </div>
                </div>
            </div>

            <div style="border-top: 1px solid #F5F5F5; width: 100%; margin-top: 20px; margin-bottom: 20px;"></div>

            <div style="margin-top: 25px;">
                <h4
                    style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol';font-size: 18px;font-weight: 400;margin: 0px;color: #6078EC;">
                    Comment</h4>

                <div
                    style="display: flex; gap: 40px; margin-top: 20px; justify-content: space-between; flex-wrap: wrap;">
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161;">
                        <strong style="font-weight: 500; color: #000000;">Status:</strong> Closed/Inactive
                    </p>
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161;">
                        <strong style="font-weight: 500; color: #000000;">Record State:</strong> 1
                    </p>
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161;">
                        <strong style="font-weight: 500; color: #000000;">Citation Number:</strong> 350
                    </p>
                </div>
            </div>

            <div style="border-top: 1px solid #F5F5F5; width: 100%; margin-top: 20px; margin-bottom: 20px;"></div>

            <div style="margin-top: 25px;">
                <h4
                    style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol';font-size: 18px;font-weight: 400;margin: 0px;color: #6078EC;">
                    Court Action</h4>

                <div style="margin-top: 20px;">
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161;">
                        <strong style="font-weight: 500; color: #000000;">Activity Type:</strong> Jury
                        trial Motor Vehicle
                    </p>
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161;">
                        <strong style="font-weight: 500; color: #000000;">Court Record Id:</strong> 03K12001588
                    </p>
                </div>
            </div>

            <div style="border-top: 1px solid #F5F5F5; width: 100%; margin-top: 20px; margin-bottom: 20px;"></div>

            <div style="margin-top: 25px;">
                <h4
                    style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol';font-size: 18px;font-weight: 400;margin: 0px;color: #6078EC;">
                    Court</h4>

                <div style="margin-top: 20px;">
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 500; color: #616161; margin-bottom: 0px;">
                        Organization Jurisdiction
                    </p>
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161; margin-top: 8px;">
                        <strong style="font-weight: 500; color: #000000;">Jurisdiction Description:</strong> Circuit
                        Court for Baltimore - Criminal System
                    </p>
                </div>
            </div>

            <div style="border-top: 1px solid #F5F5F5; width: 100%; margin-top: 20px; margin-bottom: 20px;"></div>

            <div style="margin-top: 25px;">
                <h4
                    style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol';font-size: 18px;font-weight: 400;margin: 0px;color: #6078EC;">
                    Court Charge</h4>

                <div style="margin-top: 20px;">
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 450; color: #616161; margin-bottom: 0px;">
                        <strong style="font-weight: 500; color: #000000;">Effective Date:</strong> 2011-09-13
                    </p>
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161; margin-top: 8px; margin-bottom: 0px;">
                        <strong style="font-weight: 500; color: #000000;">Charge Sequence Id:</strong> Charge Number 1
                    </p>
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161; margin-top: 8px;  margin-bottom: 0px;">
                        <strong style="font-weight: 500; color: #000000;">Number of Counts: </strong> 7
                    </p>
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161; margin-top: 8px;">
                        <strong style="font-weight: 500; color: #000000;">Charge Description: </strong> ASSUALT
                    </p>
                </div>
            </div>

            <div style="margin-top: 25px;">
                <div style="margin-top: 20px;">
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 500; color: #616161; margin-bottom: 0px;">
                        Charge Classification
                    </p>
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161; margin-top: 8px;">
                        <strong style="font-weight: 500; color: #000000;">Charge Degree: </strong> s
                    </p>
                </div>
            </div>

            <div style="margin-top: 25px;">
                <div style="margin-top: 20px;">
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 500; color: #616161; margin-bottom: 0px;">
                        Charge Disposition
                    </p>
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161; margin-top: 8px; margin-bottom: 0px;">
                        <strong style="font-weight: 500; color: #000000;">Charge Disposition Additional Information:
                        </strong> NA
                    </p>
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161;  margin-top: 8px; margin-bottom: 0px;">
                        <strong style="font-weight: 500; color: #000000;">Charge Disposition Date: </strong> 20160305
                    </p>
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161;  margin-top: 8px; margin-bottom: 0px;">
                        <strong style="font-weight: 500; color: #000000;">Disposition: </strong> GUILTY
                    </p>
                </div>
            </div>

            <div style="margin-top: 25px;">
                <div style="margin-top: 20px;">
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 500; color: #616161; margin-bottom: 0px;">
                        Charge Status
                    </p>
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161; margin-top: 8px; margin-bottom: 0px;">
                        <strong style="font-weight: 500; color: #000000;">Arrested Date: </strong> 2011-09-13
                    </p>
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161;  margin-top: 8px; margin-bottom: 0px;">
                        <strong style="font-weight: 500; color: #000000;">Charge Date: </strong> 2011-09-13
                    </p>
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161;  margin-top: 8px; margin-bottom: 0px;">
                        <strong style="font-weight: 500; color: #000000;">Expiration Date: </strong> 2015-09-13
                    </p>
                </div>
            </div>

            <div style="margin-top: 25px;">
                <div style="margin-top: 20px;">
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 500; color: #616161; margin-bottom: 0px;">
                        Charge Statute
                    </p>
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161; margin-top: 8px; margin-bottom: 0px;">
                        <strong style="font-weight: 500; color: #000000;">Statute Code Id: </strong>3
                    </p>
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161;  margin-top: 8px; margin-bottom: 0px;">
                        <strong style="font-weight: 500; color: #000000;">Level: </strong> NA
                    </p>
                </div>
            </div>

            <div style="margin-top: 25px;">
                <div style="margin-top: 20px;">
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 500; color: #616161; margin-bottom: 0px;">
                        Offense
                    </p>
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161; margin-top: 8px; margin-bottom: 0px;">
                        <strong style="font-weight: 500; color: #000000;">Offense Type Description: </strong>na
                    </p>
                </div>
            </div>

            <div style="margin-top: 25px;">
                <div style="margin-top: 20px;">
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 500; color: #616161; margin-bottom: 0px;">
                        Record Information
                    </p>
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161; margin-top: 8px; margin-bottom: 0px;">
                        <strong style="font-weight: 500; color: #000000;">Record Number:
                        </strong>TESTDISCLAIMERTESTNAME_1
                    </p>
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161;  margin-top: 8px; margin-bottom: 0px;">
                        <strong style="font-weight: 500; color: #000000;">Dataset: </strong> PACD3
                    </p>
                </div>
            </div>

            <div style="border-top: 1px solid #F5F5F5; width: 100%; margin-top: 20px; margin-bottom: 20px;"></div>

            <div style="margin-top: 25px;">
                <div style="margin-top: 20px;">
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 500; color: #000000; margin-bottom: 0px;">
                        ATTENTION
                    </p>
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161; margin-top: 8px; margin-bottom: 0px;">
                        The data or information provided is based upon information received by the Administrative Office
                        of Pennsylvania Courts ("AOPC"). AOPC makes no representation as to the accuracy, completeness
                        or utility, for any general or specific purpose, of the information provided and as such,
                        assumes no liability for inaccurate or delayed data, errors or omissions. Use of this
                        information is at your own risk. AOPC makes no representations regarding the identity of any
                        persons whose names appear in the records. User should verify that the information is accurate
                        and current by personally consulting the official record reposing in the court wherein the
                        record is maintained. Electronic case record information received from the Commonwealth of
                        Pennsylvania is not an official case record; official case records are maintained by the court
                        in which the record was filed.
                    </p>
                </div>
            </div>

        </div>

        <div style="margin-top: 40px;box-shadow: 0px 0px 10px 0px #30303014;border-radius: 10px;padding: 20px;">
            <h3
                style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol';font-size: 20px;font-weight: 500;margin: 0px;color: #212121;">
                Detailed Criminal Records</h3>

            <div style="border-top: 1px solid #F5F5F5; width: 100%; margin-top: 20px; margin-bottom: 20px;"></div>

            <div style="display: flex; justify-content: space-between; flex-wrap: wrap;
            gap: 20px;">
                <div style="">
                    <h4
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol';font-size: 18px;font-weight: 400;margin: 0px;color: #6078EC;">
                        Applicant Data as Entered </h4>
                    <h4
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol';font-size: 16px;font-weight: 500;margin: 0px;color: #212121; margin-top: 20px;">
                        Chip Portsmith </h4>
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol';font-size: 13px;font-weight: 400;margin: 0px;color: #000000;margin-top: 8px; margin-top: 15px;">
                        <strong style="font-weight: 500;">Record State:</strong> AZ
                    </p>
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol';font-size: 13px;font-weight: 400;margin: 0px;color: #000000;margin-top: 8px;">
                        <strong style="font-weight: 500;">Age:</strong> 83
                    </p>
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol';font-size: 13px;font-weight: 400;margin: 0px;color: #000000;margin-top: 8px;">
                        <strong style="font-weight: 500;">Date of Birth*:</strong> XX/XX/1941
                    </p>
                </div>

                <div style="">
                    <h4
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol';font-size: 18px;font-weight: 400;margin: 0px;color: #6078EC;">
                        Physical Features </h4>
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol';font-size: 12px;font-weight: 300;margin: 0px;color: #000000;margin-top: 8px; margin-top: 15px;">
                        Physical Details</p>

                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol';font-size: 13px;font-weight: 400;margin: 0px;color: #000000;margin-top: 8px;">
                        <strong style="font-weight: 500;">Record State:</strong> AZ
                    </p>
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol';font-size: 13px;font-weight: 400;margin: 0px;color: #000000;margin-top: 8px;">
                        <strong style="font-weight: 500;">Age:</strong> 83
                    </p>
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol';font-size: 13px;font-weight: 400;margin: 0px;color: #000000;margin-top: 8px;">
                        <strong style="font-weight: 500;">Date of Birth*:</strong> XX/XX/1941
                    </p>
                </div>
            </div>

            <div style="margin-top: 25px;">
                <h4
                    style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol';font-size: 18px;font-weight: 400;margin: 0px;color: #6078EC;">
                    Aliases</h4>

                <p
                    style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol';font-size: 13px;font-weight: 500;margin: 0px;color: #000000;margin-top: 8px;">
                    No aliases found</p>
            </div>

            <div style="border-top: 1px solid #F5F5F5; width: 100%; margin-top: 20px; margin-bottom: 20px;"></div>
            <div style="margin-top: 25px;">
                <h4
                    style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol';font-size: 18px;font-weight: 400;margin: 0px;color: #6078EC;">
                    Detailed Summary</h4>
                <div
                    style="display: flex; gap: 40px; margin-top: 20px; justify-content: space-between; flex-wrap: wrap;">
                    <div style="text-align: center; min-width: 150px; max-width: 150px;">
                        <p
                            style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 14px; color: #616161; font-weight: 500;">
                            Incident(s):</p>
                        <p
                            style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 16px; font-weight: 500; color: #212121;">
                            0</p>
                    </div>

                    <div style="text-align: center; min-width: 150px; max-width: 150px;">
                        <p
                            style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 14px; color: #616161; font-weight: 500;">
                            Booking(s):</p>
                        <p
                            style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 16px; font-weight: 500; color: #212121;">
                            0</p>
                    </div>

                    <div style="text-align: center; min-width: 150px; max-width: 150px;">
                        <p
                            style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 14px; color: #616161; font-weight: 500;">
                            Arrest(s):</p>
                        <p
                            style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 16px; font-weight: 500; color: #212121;">
                            0</p>
                    </div>
                </div>

                <div
                    style="display: flex; gap: 40px; margin-top: 20px; justify-content: space-between; flex-wrap: wrap;">
                    <div style="text-align: center; min-width: 150px; max-width: 150px;">
                        <p
                            style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 14px; color: #616161; font-weight: 500;">
                            Court Action(s):</p>
                        <p
                            style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 16px; font-weight: 500; color: #212121;">
                            1</p>
                    </div>

                    <div style="text-align: center; min-width: 150px; max-width: 150px;">
                        <p
                            style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 14px; color: #616161; font-weight: 500;">
                            Sentencing(s):</p>
                        <p
                            style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 16px; font-weight: 500; color: #212121;">
                            0</p>
                    </div>

                    <div style="text-align: center; min-width: 150px; max-width: 150px;">
                        <p
                            style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 14px; color: #616161; font-weight: 500;">
                            Supervision(s):</p>
                        <p
                            style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 16px; font-weight: 500; color: #212121;">
                            0</p>
                    </div>
                </div>
            </div>

            <div style="border-top: 1px solid #F5F5F5; width: 100%; margin-top: 20px; margin-bottom: 20px;"></div>

            <div style="margin-top: 25px;">
                <h4
                    style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol';font-size: 18px;font-weight: 400;margin: 0px;color: #6078EC;">
                    Comment</h4>

                <div
                    style="display: flex; gap: 40px; margin-top: 20px; justify-content: space-between; flex-wrap: wrap;">
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161;">
                        <strong style="font-weight: 500; color: #000000;">Status:</strong> Closed/Inactive
                    </p>
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161;">
                        <strong style="font-weight: 500; color: #000000;">Record State:</strong> 1
                    </p>
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161;">
                        <strong style="font-weight: 500; color: #000000;">Citation Number:</strong> 350
                    </p>
                </div>
            </div>

            <div style="border-top: 1px solid #F5F5F5; width: 100%; margin-top: 20px; margin-bottom: 20px;"></div>

            <div style="margin-top: 25px;">
                <h4
                    style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol';font-size: 18px;font-weight: 400;margin: 0px;color: #6078EC;">
                    Court Action</h4>

                <div style="margin-top: 20px;">
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161;">
                        <strong style="font-weight: 500; color: #000000;">Activity Type:</strong> Jury
                        trial Motor Vehicle
                    </p>
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161;">
                        <strong style="font-weight: 500; color: #000000;">Court Record Id:</strong> 03K12001588
                    </p>
                </div>
            </div>

            <div style="border-top: 1px solid #F5F5F5; width: 100%; margin-top: 20px; margin-bottom: 20px;"></div>

            <div style="margin-top: 25px;">
                <h4
                    style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol';font-size: 18px;font-weight: 400;margin: 0px;color: #6078EC;">
                    Court</h4>

                <div style="margin-top: 20px;">
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 500; color: #616161; margin-bottom: 0px;">
                        Organization Jurisdiction
                    </p>
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161; margin-top: 8px;">
                        <strong style="font-weight: 500; color: #000000;">Jurisdiction Description:</strong> Circuit
                        Court for Baltimore - Criminal System
                    </p>
                </div>
            </div>

            <div style="border-top: 1px solid #F5F5F5; width: 100%; margin-top: 20px; margin-bottom: 20px;"></div>

            <div style="margin-top: 25px;">
                <h4
                    style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol';font-size: 18px;font-weight: 400;margin: 0px;color: #6078EC;">
                    Court Charge</h4>

                <div style="margin-top: 20px;">
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 450; color: #616161; margin-bottom: 0px;">
                        <strong style="font-weight: 500; color: #000000;">Effective Date:</strong> 2011-09-13
                    </p>
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161; margin-top: 8px; margin-bottom: 0px;">
                        <strong style="font-weight: 500; color: #000000;">Charge Sequence Id:</strong>Charge Number 1
                    </p>
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161; margin-top: 8px;  margin-bottom: 0px;">
                        <strong style="font-weight: 500; color: #000000;">Number of Counts: </strong> 7
                    </p>
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161; margin-top: 8px;">
                        <strong style="font-weight: 500; color: #000000;">Charge Description: </strong> ASSUALT
                    </p>
                </div>
            </div>

            <div style="margin-top: 25px;">
                <div style="margin-top: 20px;">
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 500; color: #616161; margin-bottom: 0px;">
                        Charge Classification
                    </p>
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161; margin-top: 8px;">
                        <strong style="font-weight: 500; color: #000000;">Charge Degree: </strong> s
                    </p>
                </div>
            </div>

            <div style="margin-top: 25px;">
                <div style="margin-top: 20px;">
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 500; color: #616161; margin-bottom: 0px;">
                        Charge Disposition
                    </p>
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161; margin-top: 8px; margin-bottom: 0px;">
                        <strong style="font-weight: 500; color: #000000;">Charge Disposition Additional Information:
                        </strong> NA
                    </p>
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161;  margin-top: 8px; margin-bottom: 0px;">
                        <strong style="font-weight: 500; color: #000000;">Charge Disposition Date: </strong> 20160305
                    </p>
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161;  margin-top: 8px; margin-bottom: 0px;">
                        <strong style="font-weight: 500; color: #000000;">Disposition: </strong> GUILTY
                    </p>
                </div>
            </div>

            <div style="margin-top: 25px;">
                <div style="margin-top: 20px;">
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 500; color: #616161; margin-bottom: 0px;">
                        Charge Status
                    </p>
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161; margin-top: 8px; margin-bottom: 0px;">
                        <strong style="font-weight: 500; color: #000000;">Arrested Date: </strong> 2011-09-13
                    </p>
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161;  margin-top: 8px; margin-bottom: 0px;">
                        <strong style="font-weight: 500; color: #000000;">Charge Date: </strong> 2011-09-13
                    </p>
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161;  margin-top: 8px; margin-bottom: 0px;">
                        <strong style="font-weight: 500; color: #000000;">Expiration Date: </strong> 2015-09-13
                    </p>
                </div>
            </div>

            <div style="margin-top: 25px;">
                <div style="margin-top: 20px;">
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 500; color: #616161; margin-bottom: 0px;">
                        Charge Statute
                    </p>
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161; margin-top: 8px; margin-bottom: 0px;">
                        <strong style="font-weight: 500; color: #000000;">Statute Code Id: </strong>3
                    </p>
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161;  margin-top: 8px; margin-bottom: 0px;">
                        <strong style="font-weight: 500; color: #000000;">Level: </strong> NA
                    </p>
                </div>
            </div>

            <div style="margin-top: 25px;">
                <div style="margin-top: 20px;">
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 500; color: #616161; margin-bottom: 0px;">
                        Offense
                    </p>
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161; margin-top: 8px; margin-bottom: 0px;">
                        <strong style="font-weight: 500; color: #000000;">Offense Type Description: </strong>na
                    </p>
                </div>
            </div>

            <div style="margin-top: 25px;">
                <div style="margin-top: 20px;">
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 500; color: #616161; margin-bottom: 0px;">
                        Record Information
                    </p>
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161; margin-top: 8px; margin-bottom: 0px;">
                        <strong style="font-weight: 500; color: #000000;">Record Number:
                        </strong>TESTDISCLAIMERTESTNAME_1
                    </p>
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161;  margin-top: 8px; margin-bottom: 0px;">
                        <strong style="font-weight: 500; color: #000000;">Dataset: </strong> PACD3
                    </p>
                </div>
            </div>

            <div style="border-top: 1px solid #F5F5F5; width: 100%; margin-top: 20px; margin-bottom: 20px;"></div>

            <div style="margin-top: 25px;">
                <div style="margin-top: 20px;">
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 500; color: #000000; margin-bottom: 0px;">
                        ATTENTION
                    </p>
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161; margin-top: 8px; margin-bottom: 0px;">
                        The data or information provided is based upon information received by the Administrative Office
                        of Pennsylvania Courts ("AOPC"). AOPC makes no representation as to the accuracy, completeness
                        or utility, for any general or specific purpose, of the information provided and as such,
                        assumes no liability for inaccurate or delayed data, errors or omissions. Use of this
                        information is at your own risk. AOPC makes no representations regarding the identity of any
                        persons whose names appear in the records. User should verify that the information is accurate
                        and current by personally consulting the official record reposing in the court wherein the
                        record is maintained. Electronic case record information received from the Commonwealth of
                        Pennsylvania is not an official case record; official case records are maintained by the court
                        in which the record was filed.
                    </p>
                </div>
            </div>
        </div>

        <!-- Detailed Criminal Records strat -->
        <div style="margin-top: 40px;box-shadow: 0px 0px 10px 0px #30303014;border-radius: 10px;padding: 20px;">
            <h3
                style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol';font-size: 20px;font-weight: 500;margin: 0px;color: #212121;">
                Detailed Criminal Records</h3>

            <div style="border-top: 1px solid #F5F5F5; width: 100%; margin-top: 20px; margin-bottom: 20px;"></div>

            <div style="display: flex; justify-content: space-between; flex-wrap: wrap;
            gap: 20px;">
                <div style="">
                    <h4
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol';font-size: 18px;font-weight: 400;margin: 0px;color: #6078EC;">
                        Applicant Data as Entered </h4>
                    <h4
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol';font-size: 16px;font-weight: 500;margin: 0px;color: #212121; margin-top: 20px;">
                        Chip Portsmith </h4>
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol';font-size: 13px;font-weight: 400;margin: 0px;color: #000000;margin-top: 8px; margin-top: 15px;">
                        <strong style="font-weight: 500;">Record State:</strong> AZ
                    </p>
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol';font-size: 13px;font-weight: 400;margin: 0px;color: #000000;margin-top: 8px;">
                        <strong style="font-weight: 500;">Age:</strong> 83
                    </p>
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol';font-size: 13px;font-weight: 400;margin: 0px;color: #000000;margin-top: 8px;">
                        <strong style="font-weight: 500;">Date of Birth*:</strong> XX/XX/1941
                    </p>
                </div>

                <div style="">
                    <h4
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol';font-size: 18px;font-weight: 400;margin: 0px;color: #6078EC;">
                        Physical Features </h4>
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol';font-size: 12px;font-weight: 300;margin: 0px;color: #000000;margin-top: 8px; margin-top: 15px;">
                        Physical Details</p>

                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol';font-size: 13px;font-weight: 400;margin: 0px;color: #000000;margin-top: 8px;">
                        <strong style="font-weight: 500;">Record State:</strong> AZ
                    </p>
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol';font-size: 13px;font-weight: 400;margin: 0px;color: #000000;margin-top: 8px;">
                        <strong style="font-weight: 500;">Age:</strong> 83
                    </p>
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol';font-size: 13px;font-weight: 400;margin: 0px;color: #000000;margin-top: 8px;">
                        <strong style="font-weight: 500;">Date of Birth*:</strong> XX/XX/1941
                    </p>
                </div>
            </div>

            <div style="margin-top: 25px;">
                <h4
                    style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol';font-size: 18px;font-weight: 400;margin: 0px;color: #6078EC;">
                    Aliases</h4>

                <p
                    style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol';font-size: 13px;font-weight: 500;margin: 0px;color: #000000;margin-top: 8px;">
                    No aliases found</p>
            </div>

            <div style="border-top: 1px solid #F5F5F5; width: 100%; margin-top: 20px; margin-bottom: 20px;"></div>
            <div style="margin-top: 25px;">
                <h4
                    style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol';font-size: 18px;font-weight: 400;margin: 0px;color: #6078EC;">
                    Detailed Summary</h4>
                <div
                    style="display: flex; gap: 40px; margin-top: 20px; justify-content: space-between; flex-wrap: wrap;">
                    <div style="text-align: center; min-width: 150px; max-width: 150px;">
                        <p
                            style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 14px; color: #616161; font-weight: 500;">
                            Incident(s):</p>
                        <p
                            style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 16px; font-weight: 500; color: #212121;">
                            0</p>
                    </div>

                    <div style="text-align: center; min-width: 150px; max-width: 150px;">
                        <p
                            style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 14px; color: #616161; font-weight: 500;">
                            Booking(s):</p>
                        <p
                            style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 16px; font-weight: 500; color: #212121;">
                            0</p>
                    </div>

                    <div style="text-align: center; min-width: 150px; max-width: 150px;">
                        <p
                            style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 14px; color: #616161; font-weight: 500;">
                            Arrest(s):</p>
                        <p
                            style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 16px; font-weight: 500; color: #212121;">
                            0</p>
                    </div>
                </div>

                <div
                    style="display: flex; gap: 40px; margin-top: 20px; justify-content: space-between; flex-wrap: wrap;">
                    <div style="text-align: center; min-width: 150px; max-width: 150px;">
                        <p
                            style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 14px; color: #616161; font-weight: 500;">
                            Court Action(s):</p>
                        <p
                            style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 16px; font-weight: 500; color: #212121;">
                            1</p>
                    </div>

                    <div style="text-align: center; min-width: 150px; max-width: 150px;">
                        <p
                            style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 14px; color: #616161; font-weight: 500;">
                            Sentencing(s):</p>
                        <p
                            style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 16px; font-weight: 500; color: #212121;">
                            0</p>
                    </div>

                    <div style="text-align: center; min-width: 150px; max-width: 150px;">
                        <p
                            style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 14px; color: #616161; font-weight: 500;">
                            Supervision(s):</p>
                        <p
                            style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 16px; font-weight: 500; color: #212121;">
                            0</p>
                    </div>
                </div>
            </div>

            <div style="border-top: 1px solid #F5F5F5; width: 100%; margin-top: 20px; margin-bottom: 20px;"></div>

            <div style="margin-top: 25px;">
                <h4
                    style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol';font-size: 18px;font-weight: 400;margin: 0px;color: #6078EC;">
                    Comment</h4>

                <div
                    style="display: flex; gap: 40px; margin-top: 20px; justify-content: space-between; flex-wrap: wrap;">
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161;">
                        <strong style="font-weight: 500; color: #000000;">Status:</strong> Closed/Inactive
                    </p>
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161;">
                        <strong style="font-weight: 500; color: #000000;">Record State:</strong> 1
                    </p>
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161;">
                        <strong style="font-weight: 500; color: #000000;">Citation Number:</strong> 350
                    </p>
                </div>
            </div>

            <div style="border-top: 1px solid #F5F5F5; width: 100%; margin-top: 20px; margin-bottom: 20px;"></div>

            <div style="margin-top: 25px;">
                <h4
                    style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol';font-size: 18px;font-weight: 400;margin: 0px;color: #6078EC;">
                    Court Action</h4>

                <div style="margin-top: 20px;">
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161;">
                        <strong style="font-weight: 500; color: #000000;">Activity Type:</strong> Jury
                        trial Motor Vehicle
                    </p>
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161;">
                        <strong style="font-weight: 500; color: #000000;">Court Record Id:</strong> 03K12001588
                    </p>
                </div>
            </div>

            <div style="border-top: 1px solid #F5F5F5; width: 100%; margin-top: 20px; margin-bottom: 20px;"></div>

            <div style="margin-top: 25px;">
                <h4
                    style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol';font-size: 18px;font-weight: 400;margin: 0px;color: #6078EC;">
                    Court</h4>

                <div style="margin-top: 20px;">
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 500; color: #616161; margin-bottom: 0px;">
                        Organization Jurisdiction
                    </p>
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161; margin-top: 8px;">
                        <strong style="font-weight: 500; color: #000000;">Jurisdiction Description:</strong> Circuit
                        Court for Baltimore - Criminal System
                    </p>
                </div>
            </div>

            <div style="border-top: 1px solid #F5F5F5; width: 100%; margin-top: 20px; margin-bottom: 20px;"></div>

            <div style="margin-top: 25px;">
                <h4
                    style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol';font-size: 18px;font-weight: 400;margin: 0px;color: #6078EC;">
                    Court Charge</h4>

                <div style="margin-top: 20px;">
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 450; color: #616161; margin-bottom: 0px;">
                        <strong style="font-weight: 500; color: #000000;">Effective Date:</strong> 2011-09-13
                    </p>
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161; margin-top: 8px; margin-bottom: 0px;">
                        <strong style="font-weight: 500; color: #000000;">Charge Sequence Id:</strong>Charge Number 1
                    </p>
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161; margin-top: 8px;  margin-bottom: 0px;">
                        <strong style="font-weight: 500; color: #000000;">Number of Counts: </strong> 7
                    </p>
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161; margin-top: 8px;">
                        <strong style="font-weight: 500; color: #000000;">Charge Description: </strong> ASSUALT
                    </p>
                </div>
            </div>

            <div style="margin-top: 25px;">
                <div style="margin-top: 20px;">
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 500; color: #616161; margin-bottom: 0px;">
                        Charge Classification
                    </p>
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161; margin-top: 8px;">
                        <strong style="font-weight: 500; color: #000000;">Charge Degree: </strong> s
                    </p>
                </div>
            </div>

            <div style="margin-top: 25px;">
                <div style="margin-top: 20px;">
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 500; color: #616161; margin-bottom: 0px;">
                        Charge Disposition
                    </p>
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161; margin-top: 8px; margin-bottom: 0px;">
                        <strong style="font-weight: 500; color: #000000;">Charge Disposition Additional Information:
                        </strong> NA
                    </p>
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161;  margin-top: 8px; margin-bottom: 0px;">
                        <strong style="font-weight: 500; color: #000000;">Charge Disposition Date: </strong> 20160305
                    </p>
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161;  margin-top: 8px; margin-bottom: 0px;">
                        <strong style="font-weight: 500; color: #000000;">Disposition: </strong> GUILTY
                    </p>
                </div>
            </div>

            <div style="margin-top: 25px;">
                <div style="margin-top: 20px;">
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 500; color: #616161; margin-bottom: 0px;">
                        Charge Status
                    </p>
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161; margin-top: 8px; margin-bottom: 0px;">
                        <strong style="font-weight: 500; color: #000000;">Arrested Date: </strong> 2011-09-13
                    </p>
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161;  margin-top: 8px; margin-bottom: 0px;">
                        <strong style="font-weight: 500; color: #000000;">Charge Date: </strong> 2011-09-13
                    </p>
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161;  margin-top: 8px; margin-bottom: 0px;">
                        <strong style="font-weight: 500; color: #000000;">Expiration Date: </strong> 2015-09-13
                    </p>
                </div>
            </div>

            <div style="margin-top: 25px;">
                <div style="margin-top: 20px;">
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 500; color: #616161; margin-bottom: 0px;">
                        Charge Statute
                    </p>
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161; margin-top: 8px; margin-bottom: 0px;">
                        <strong style="font-weight: 500; color: #000000;">Statute Code Id: </strong>3
                    </p>
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161;  margin-top: 8px; margin-bottom: 0px;">
                        <strong style="font-weight: 500; color: #000000;">Level: </strong> NA
                    </p>
                </div>
            </div>

            <div style="margin-top: 25px;">
                <div style="margin-top: 20px;">
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 500; color: #616161; margin-bottom: 0px;">
                        Offense
                    </p>
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161; margin-top: 8px; margin-bottom: 0px;">
                        <strong style="font-weight: 500; color: #000000;">Offense Type Description: </strong>na
                    </p>
                </div>
            </div>

            <div style="margin-top: 25px;">
                <div style="margin-top: 20px;">
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 500; color: #616161; margin-bottom: 0px;">
                        Record Information
                    </p>
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161; margin-top: 8px; margin-bottom: 0px;">
                        <strong style="font-weight: 500; color: #000000;">Record Number:
                        </strong>TESTDISCLAIMERTESTNAME_1
                    </p>
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161;  margin-top: 8px; margin-bottom: 0px;">
                        <strong style="font-weight: 500; color: #000000;">Dataset: </strong> PACD3
                    </p>
                </div>
            </div>

            <div style="border-top: 1px solid #F5F5F5; width: 100%; margin-top: 20px; margin-bottom: 20px;"></div>

            <div style="margin-top: 25px;">
                <div style="margin-top: 20px;">
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 500; color: #000000; margin-bottom: 0px;">
                        A Summary of Your Rights Under the Fair Credit Reporting Act
                    </p>
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161; margin-top: 8px; margin-bottom: 0px;">
                        Para informacion en espanol, visite www.consumerfinance.gov/learnmore o escribe a la Consumer
                        Financial Protection Bureau,1700 G Street N.W., Washington, DC 20552.
                    </p>
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161; margin-top: 5px; margin-bottom: 0px;">
                        The federal Fair Credit Reporting Act (FCRA) promotes the accuracy, fairness, and privacy of
                        information in the files of consumer reporting agencies. There are many types of consumer
                        reporting agencies, including credit bureaus and specialty agencies (such as agencies that sell
                        information about check writing histories, medical records, and rental history records).
                    </p>
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161; margin-top: 5px; margin-bottom: 0px;">
                        For more information, including information about additional rights, go
                        to www.consumerfinance.gov/learnmore, or write to: Consumer Financial Protection Bureau, 1700 G
                        Street N.W., Washington, DC 20552.
                    </p>
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161; margin-top: 5px; margin-bottom: 0px; display: list-item; margin-left: 10px;">
                        You must be told if information in your file has been used against you. Anyone who uses a credit
                        report or another type of consumer report to deny your application for credit, insurance, or
                        employment -- or to take another adverse action against you -- must tell you, and must give you
                        the name, address, and phone number of the agency that provided the information.
                    </p>
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161; margin-top: 5px; margin-bottom: 0px; display: list-item; margin-left: 10px;">
                        You have the right to know what is in your file. You may request and obtain all the information
                        about you in the files of a consumer reporting agency (your "file disclosure"). You will be
                        required to provide proper identification, which may include your Social Security Number. In
                        many cases, the disclosure will be free. You are entitled to a free file disclosure if:
                    </p>
                    <div style="padding-left: 12px; padding-top: 5px; padding-bottom: 5px;">
                        <p
                            style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161; margin-top: 5px; margin-bottom: 0px; display: list-item; margin-left: 10px;">
                            A person has taken adverse action against you because of information in your credit report;
                        </p>
                        <p
                            style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161; margin-top: 5px; margin-bottom: 0px; display: list-item; margin-left: 10px;">
                            You are the victim of identity theft and place a fraud alert in your file;
                        </p>
                        <p
                            style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161; margin-top: 5px; margin-bottom: 0px; display: list-item; margin-left: 10px;">
                            Your file contains inaccurate information as a result of fraud;
                        </p>
                        <p
                            style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161; margin-top: 5px; margin-bottom: 0px; display: list-item; margin-left: 10px;">
                            You are on public assistance;
                        </p>
                        <p
                            style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161; margin-top: 5px; margin-bottom: 0px; display: list-item; margin-left: 10px;">
                            You are unemployed but expect to apply for employment within 60 days.
                        </p>
                    </div>
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161; margin-top: 5px; margin-bottom: 0px; display: list-item; margin-left: 10px;">
                        In addition, all consumers are entitled to one free disclosure every 12 months upon request from
                        each nationwide credit bureau and from nationwide specialty consumer reporting agencies.
                        See www.consumerfinance.gov/learnmore for more information.
                    </p>
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161; margin-top: 5px; margin-bottom: 0px; display: list-item; margin-left: 10px;">
                        You have the right to ask for a credit score. Credit scores are numerical summaries of your
                        credit-worthiness based on information from credit bureaus. You may request a credit score from
                        consumer reporting agencies that create scores or distribute scores used in residential real
                        property loans, but you will have to pay for it. In some mortgage transactions, you will receive
                        credit score information for free from the mortgage lender.
                    </p>
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161; margin-top: 5px; margin-bottom: 0px; display: list-item; margin-left: 10px;">
                        You have the right to dispute incomplete or inaccurate information. If you identify information
                        in your file that is incomplete or inaccurate, and report it to the consumer reporting agency,
                        the agency must investigate unless your dispute is frivolous.
                        See www.consumerfinance.gov/learnmore for an explanation of dispute procedures.
                    </p>
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161; margin-top: 5px; margin-bottom: 0px; display: list-item; margin-left: 10px;">
                        Consumer reporting agencies must correct or delete inaccurate, incomplete, or unverifiable
                        information. Inaccurate, incomplete, or unverifiable information must be removed or corrected,
                        usually within 30 days. However a consumer reporting agency may continue to report information
                        it has verified as accurate.
                    </p>
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161; margin-top: 5px; margin-bottom: 0px; display: list-item; margin-left: 10px;">
                        Consumer reporting agencies may not report outdated negative information. In most cases, a
                        consumer reporting agency may not report negative information that is more than seven years old,
                        or bankruptcies that are more than 10 years old.
                    </p>
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161; margin-top: 5px; margin-bottom: 0px; display: list-item; margin-left: 10px;">
                        Access to your file is limited. A consumer reporting agency may provide information about you
                        only to people with a valid need - usually to consider an application with a creditor, insurer,
                        employer, landlord, or other business. The FCRA specifies those with a valid need for access.
                    </p>
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161; margin-top: 5px; margin-bottom: 0px; display: list-item; margin-left: 10px;">
                        You must give your consent for reports to be provided to employers. A consumer reporting agency
                        may not give out information about you to your employer, or a potential employer, without your
                        written consent given to the employer. Written consent generally is not required in the trucking
                        industry. For more information, go to www.consumerfinance.gov/learnmore.
                    </p>
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161; margin-top: 5px; margin-bottom: 0px; display: list-item; margin-left: 10px;">
                        You may limit "prescreened" offers of credit and insurance you get based on information in your
                        credit report. Unsolicited "prescreened" offers for credit and insurance must include a
                        toll-free phone number you can call if you choose to remove your name and address from the lists
                        these offers are based on. You may opt-out with the nationwide credit bureaus at 1-888-567-8688
                        (888-5OPTOUT).
                    </p>
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161; margin-top: 5px; margin-bottom: 0px; display: list-item; margin-left: 10px;">
                        CONSUMERS HAVE THE RIGHT TO OBTAIN A SECURITY FREEZE. You have a right to place a â€œsecurity
                        freezeâ€ on your credit report, which will prohibit a consumer reporting agency from releasing
                        information in your credit report without your express authorization. The security freeze is
                        designed to prevent credit, loans, and services from being approved in your name without your
                        consent. However, you should be aware that using a security freeze to take control over who gets
                        access to the personal and financial information in your credit report may delay, interfere
                        with, or prohibit the timely approval of any subsequent request or application you make
                        regarding a new loan, credit, mortgage, or any other account involving the extension of credit.
                    </p>
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161; margin-top: 5px; margin-bottom: 0px; display: list-item; margin-left: 10px;">
                        A security freeze does not apply to a person or entity, or its affiliates, or collection
                        agencies acting on behalf of the person or entity, with which you have an existing account that
                        requests information in your credit report for the purposes of reviewing or collecting the
                        account. Reviewing the account includes activities related to account maintenance, monitoring,
                        credit line increases, and account upgrades and enhancements.
                    </p>
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161; margin-top: 5px; margin-bottom: 0px; display: list-item; margin-left: 10px;">
                        As an alternative to a security freeze, you have the right to place an initial or extended fraud
                        alert on your credit file at no cost. An initial fraud alert is a 1-year alert that is placed on
                        a consumerâ€™s credit file. Upon seeing a fraud alert display on a consumerâ€™s credit file, a
                        business is required to take steps to verify the consumerâ€™s identity before extending new
                        credit. If you are a victim of identity theft, you are entitled to an extended fraud alert,
                        which is a fraud alert lasting 7 years.
                    </p>
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161; margin-top: 5px; margin-bottom: 0px; display: list-item; margin-left: 10px;">
                        You may seek damages from violators. If a consumer reporting agency, or, in some cases, a user
                        of consumer reports or a furnisher of information to a consumer reporting agency violates the
                        FCRA, you may be able to sue in state or federal court. You may also have the right to file suit
                        under state law.
                    </p>
                    <p
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161; margin-top: 5px; margin-bottom: 0px; display: list-item; margin-left: 10px;">
                        Identity theft victims and active duty military personnel have additional rights. For more
                        information, visit www.consumerfinance.gov/learnmore.
                    </p>

                    <div style="margin-top: 25px;">
                        <p
                            style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161; margin-top: 5px; margin-bottom: 0px;">
                            TYPE OF BUSINESS:
                        </p>
                        <p
                            style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161; margin-top: 5px; margin-bottom: 0px;">
                            CONTACT:
                        </p>
                        <p
                            style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161; margin-top: 5px; margin-bottom: 0px;">
                            1.a. Banks, savings associations, and credit unions with total assets of over $10 billion
                            and their affiliates
                        </p>
                    </div>

                    <div style="margin-top: 25px;">
                        <p
                            style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161; margin-top: 5px; margin-bottom: 0px;">
                            Bureau of Consumer Financial Protection
                        </p>
                        <p
                            style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161; margin-top: 5px; margin-bottom: 0px;">
                            1700 G Street NW
                        </p>
                        <p
                            style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161; margin-top: 5px; margin-bottom: 0px;">
                            Washington, DC 20552
                        </p>
                    </div>

                    <div style="margin-top: 25px;">
                        <p
                            style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161; margin-top: 5px; margin-bottom: 0px;">
                            b. Such affiliates that are not banks, savings associations, or credit unions also should
                            list, in addition to the Bureau:
                        </p>
                    </div>

                    <div style="margin-top: 25px;">
                        <p
                            style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161; margin-top: 5px; margin-bottom: 0px;">
                            Federal Trade Commission
                        </p>
                        <p
                            style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161; margin-top: 5px; margin-bottom: 0px;">
                            Consumer Response Center - FCRA
                        </p>
                        <p
                            style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161; margin-top: 5px; margin-bottom: 0px;">
                            Washington, DC 20580
                        </p>
                        <p
                            style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161; margin-top: 5px; margin-bottom: 0px;">
                            1-877-382-4357
                        </p>
                    </div>

                    <div style="margin-top: 25px;">
                        <p
                            style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161; margin-top: 5px; margin-bottom: 0px;">
                            2. To the extent not included in item 1 above:
                        </p>
                    </div>

                    <div style="margin-top: 25px;">
                        <p
                            style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161; margin-top: 5px; margin-bottom: 0px;">
                            a. National banks, federal savings associations, and federal branches and federal agencies
                            of foreign banks
                            Office of the Comptroller of the Currency
                        </p>
                    </div>

                    <div style="margin-top: 25px;">
                        <p
                            style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161; margin-top: 5px; margin-bottom: 0px;">
                            Customer Assistance Group
                        </p>
                        <p
                            style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161; margin-top: 5px; margin-bottom: 0px;">
                            1301 McKinney Street, Suite 3450
                        </p>
                        <p
                            style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161; margin-top: 5px; margin-bottom: 0px;">
                            Houston, TX 77010-9050
                        </p>
                    </div>

                    <div style="margin-top: 25px;">
                        <p
                            style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161; margin-top: 5px; margin-bottom: 0px;">
                            b. State member banks, branches and agencies of foreign banks (other than federal branches,
                            federal agencies, and insured state branches of foreign banks), commercial lending companies
                            owned or controlled by foreign banks, and organizations operating under section 25 or 25A of
                            the Federal Reserve Act
                        </p>
                    </div>

                    <div style="margin-top: 25px;">
                        <p
                            style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161; margin-top: 5px; margin-bottom: 0px;">
                            Federal Reserve Consumer Help (FRCH)
                        </p>
                        <p
                            style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161; margin-top: 5px; margin-bottom: 0px;">
                            PO Box 1200
                        </p>
                        <p
                            style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161; margin-top: 5px; margin-bottom: 0px;">
                            Minneapolis, MN 55480
                        </p>
                    </div>

                    <div style="margin-top: 25px;">
                        <p
                            style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161; margin-top: 5px; margin-bottom: 0px;">
                            c. Nonmember Insured Banks, Insured State Branches of Foreign Banks, and Insured state
                            savings associations
                        </p>
                    </div>

                    <div style="margin-top: 25px;">
                        <p
                            style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161; margin-top: 5px; margin-bottom: 0px;">
                            FDIC Consumer Response Center
                        </p>
                        <p
                            style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161; margin-top: 5px; margin-bottom: 0px;">
                            1100 Walnut Street, Box #11
                        </p>
                        <p
                            style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161; margin-top: 5px; margin-bottom: 0px;">
                            Kansas City, MO 64106
                        </p>
                    </div>

                    <div style="margin-top: 25px;">
                        <p
                            style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161; margin-top: 5px; margin-bottom: 0px;">
                            d. Federal credit unions
                        </p>
                    </div>

                    <div style="margin-top: 25px;">
                        <p
                            style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161; margin-top: 5px; margin-bottom: 0px;">
                            National Credit Union Administration
                        </p>
                        <p
                            style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161; margin-top: 5px; margin-bottom: 0px;">
                            Office of Consumer Protection (OCP)
                        </p>
                        <p
                            style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161; margin-top: 5px; margin-bottom: 0px;">
                            Division of Consumer Compliance and Outreach (DCCO)
                        </p>
                        <p
                            style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161; margin-top: 5px; margin-bottom: 0px;">
                            1775 Duke Street
                        </p>
                        <p
                            style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161; margin-top: 5px; margin-bottom: 0px;">
                            Alexandria, VA 22314
                        </p>
                    </div>

                    <div style="margin-top: 25px;">
                        <p
                            style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161; margin-top: 5px; margin-bottom: 0px;">
                            3. Air carriers
                        </p>
                    </div>

                    <div style="margin-top: 25px;">
                        <p
                            style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161; margin-top: 5px; margin-bottom: 0px;">
                            Asst. General Counsel for Aviation Enforcement & Proceedings
                        </p>
                        <p
                            style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161; margin-top: 5px; margin-bottom: 0px;">
                            Aviation Consumer Protection Division
                        </p>
                        <p
                            style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161; margin-top: 5px; margin-bottom: 0px;">
                            Department of Transportation
                        </p>
                        <p
                            style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161; margin-top: 5px; margin-bottom: 0px;">
                            1200 New Jersey Avenue, S.E.
                        </p>
                        <p
                            style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161; margin-top: 5px; margin-bottom: 0px;">
                            Washington, DC 20590
                        </p>
                        <p
                            style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161; margin-top: 5px; margin-bottom: 0px;">
                            1-202-366-1306
                        </p>
                    </div>

                    <div style="margin-top: 25px;">
                        <p
                            style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161; margin-top: 5px; margin-bottom: 0px;">
                            4. Creditors Subject to Surface Transportation Board
                        </p>
                    </div>

                    <div style="margin-top: 25px;">
                        <p
                            style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161; margin-top: 5px; margin-bottom: 0px;">
                            Office of Proceedings, Surface Transportation Board
                        </p>
                        <p
                            style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161; margin-top: 5px; margin-bottom: 0px;">
                            Department of Transportation
                        </p>
                        <p
                            style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161; margin-top: 5px; margin-bottom: 0px;">
                            395 E Street, S.W.
                        </p>
                        <p
                            style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161; margin-top: 5px; margin-bottom: 0px;">
                            Washington, DC 20423
                        </p>
                    </div>

                    <div style="margin-top: 25px;">
                        <p
                            style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161; margin-top: 5px; margin-bottom: 0px;">
                            5. Creditors subject to Packers and Stockyards Act, 1921
                        </p>
                    </div>

                    <div style="margin-top: 25px;">
                        <p
                            style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161; margin-top: 5px; margin-bottom: 0px;">
                            Nearest Packers and Stockyards Administration area supervisor
                        </p>
                    </div>

                    <div style="margin-top: 25px;">
                        <p
                            style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161; margin-top: 5px; margin-bottom: 0px;">
                            6. Small Business Investment Companies
                        </p>
                    </div>

                    <div style="margin-top: 25px;">
                        <p
                            style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161; margin-top: 5px; margin-bottom: 0px;">
                            Associate Deputy Administrator for Capital Access
                        </p>
                        <p
                            style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161; margin-top: 5px; margin-bottom: 0px;">
                            United States Small Business Administration
                        </p>
                        <p
                            style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161; margin-top: 5px; margin-bottom: 0px;">
                            409 Third Street, SW, 8th Floor
                        </p>
                        <p
                            style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161; margin-top: 5px; margin-bottom: 0px;">
                            Washington, DC 20416
                        </p>
                    </div>

                    <div style="margin-top: 25px;">
                        <p
                            style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161; margin-top: 5px; margin-bottom: 0px;">
                            7. Brokers and Dealers
                        </p>
                    </div>

                    <div style="margin-top: 25px;">
                        <p
                            style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161; margin-top: 5px; margin-bottom: 0px;">
                            Securities and Exchange Commission
                        </p>
                        <p
                            style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161; margin-top: 5px; margin-bottom: 0px;">
                            100 F St NE
                        </p>
                        <p
                            style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161; margin-top: 5px; margin-bottom: 0px;">
                            Washington, DC 20549
                        </p>
                    </div>

                    <div style="margin-top: 25px;">
                        <p
                            style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161; margin-top: 5px; margin-bottom: 0px;">
                            8. Federal Land Banks, Federal Land Bank Associations, Federal Intermediate Credit Banks,
                            and Production Credit Associations
                        </p>
                    </div>

                    <div style="margin-top: 25px;">
                        <p
                            style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161; margin-top: 5px; margin-bottom: 0px;">
                            Farm Credit Administration
                        </p>
                        <p
                            style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161; margin-top: 5px; margin-bottom: 0px;">
                            1501 Farm Credit Drive
                        </p>
                        <p
                            style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161; margin-top: 5px; margin-bottom: 0px;">
                            McLean, VA 22102-5090
                        </p>
                    </div>

                    <div style="margin-top: 25px;">
                        <p
                            style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161; margin-top: 5px; margin-bottom: 0px;">
                            9. Retailers, Finance Companies, and All Other Creditors Not Listed Above
                        </p>
                    </div>

                    <div style="margin-top: 25px;">
                        <p
                            style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161; margin-top: 5px; margin-bottom: 0px;">
                            FTC Regional Office for region in which the creditor operates or Federal Trade Commission:
                            Consumer Response Center - FCRA
                        </p>
                        <p
                            style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161; margin-top: 5px; margin-bottom: 0px;">
                            Washington, DC 20580
                        </p>
                        <p
                            style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161; margin-top: 5px; margin-bottom: 0px;">
                            1-877-382-4357
                        </p>
                        <p
                            style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161; margin-top: 5px; margin-bottom: 0px;">
                            * If the Date of Birth has : "X" = Matched Data, "-" = Data Not Available, "0-9" =
                            Mismatched
                        </p>
                        <p
                            style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 13px; font-weight: 400; color: #616161; margin-top: 5px; margin-bottom: 0px;">© 2024 TransUnion Rental Screening Solutions, Inc. All Rights Reserved. 3/7/2024 4:50:18 AM
                        </p>
                    </div>

                </div>
            </div>
        </div>

    </div>
</body>

</html>