<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CompanyDetails;
use Intervention\Image\Facades\Image;

class CompanyDetailsController extends Controller
{
    public function index()
    {
        $data = CompanyDetails::firstOrCreate();
        return spa('admin.company.index', compact('data'));
    }

    public function update(Request $request)
    {
        $data = CompanyDetails::first();

        $request->validate([
            'company_name' => 'required|string|max:255',
            'business_name' => 'nullable|max:255',
            'email1' => 'nullable|email|max:255',
            'email2' => 'nullable|email|max:255',
            'phone1' => 'nullable|string|max:20',
            'phone2' => 'nullable|string|max:20',
            'phone3' => 'nullable|string|max:20',
            'phone4' => 'nullable|string|max:20',
            'whatsapp' => 'nullable|string|max:20',
            'address1' => 'nullable|string|max:255',
            'address2' => 'nullable|string|max:255',
            'website' => 'nullable|string|max:255',
            'facebook' => 'nullable|string|max:255',
            'instagram' => 'nullable|string|max:255',
            'twitter' => 'nullable|string|max:255',
            'linkedin' => 'nullable|string|max:255',
            'youtube' => 'nullable|string|max:255',
            'tiktok' => 'nullable|string|max:255',
            'tawkto' => 'nullable|string|max:255',
            'vat_percent' => 'nullable|integer',
            'appstore_link' => 'nullable|string|max:255',
            'google_play_link' => 'nullable|string|max:255',
            'currency' => 'nullable|string|max:10',
            'google_map' => 'nullable|string',
            'company_reg_number' => 'nullable|string',
            'vat_number' => 'nullable|string',
            'account_number' => 'nullable|string',
            'sort_code' => 'nullable|string',
            'bank' => 'nullable|string',
            'opening_time' => 'nullable|string',
            'fav_icon' => 'nullable|mimes:jpeg,png,jpg,gif,svg,webp|max:2048',
            'company_logo' => 'nullable|mimes:jpeg,png,jpg,gif,svg,webp|max:2048',
            'footer_logo' => 'nullable|mimes:jpeg,png,jpg,gif,svg,webp|max:2048',
            'meta_og_image' => 'nullable|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'about_us' => 'nullable|string',
            'header_content' => 'nullable|string',
            'footer_content' => 'nullable|string',
            'copyright' => 'nullable|string',
            'privacy_policy' => 'nullable|string',
            'terms_and_conditions' => 'nullable|string',
            'refund_policy' => 'nullable|string',
            'bank_info' => 'nullable|string',
            'google_site_verification' => 'nullable|string|max:255',
            'google_analytics' => 'nullable|string',
            'google_tag_manager' => 'nullable|string',
            'facebook_pixel_id' => 'nullable|string',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string',
            'meta_keywords' => 'nullable|string',
            'meta_og_title' => 'nullable|string|max:255',
        ]);

        $path = public_path('uploads/company/');
        if (!file_exists($path)) mkdir($path, 0755, true);

        // Handle Fav Icon Upload
        if ($request->hasFile('fav_icon')) {
            if ($data->fav_icon && file_exists(public_path('uploads/company/' . $data->fav_icon))) {
                unlink(public_path('uploads/company/' . $data->fav_icon));
            }
            $favIconName = rand(100000, 999999) . '_fav_icon.' . $request->fav_icon->extension();
            $request->fav_icon->move(public_path('uploads/company'), $favIconName);
            $data->fav_icon = $favIconName;
        }

        // Handle Company Logo Upload
        if ($request->hasFile('company_logo')) {
            if ($data->company_logo && file_exists(public_path('uploads/company/' . $data->company_logo))) {
                unlink(public_path('uploads/company/' . $data->company_logo));
            }
            $companyLogoName = rand(100000, 999999) . '_company_logo.' . $request->company_logo->extension();
            $request->company_logo->move(public_path('uploads/company'), $companyLogoName);
            $data->company_logo = $companyLogoName;
        }

        // Handle Footer Logo Upload
        if ($request->hasFile('footer_logo')) {
            if ($data->footer_logo && file_exists(public_path('uploads/company/' . $data->footer_logo))) {
                unlink(public_path('uploads/company/' . $data->footer_logo));
            }
            $footerLogoName = rand(100000, 999999) . '_footer_logo.' . $request->footer_logo->extension();
            $request->footer_logo->move(public_path('uploads/company'), $footerLogoName);
            $data->footer_logo = $footerLogoName;
        }

        // Handle Meta OG Image Upload
        if ($request->hasFile('meta_og_image')) {
            $metaOgImage = $request->file('meta_og_image');
            $metaOgImageName = 'meta_og_' . time() . '.webp';
            $path = public_path('uploads/company/meta/');

            if (!file_exists($path)) {
                mkdir($path, 0755, true);
            }

            if ($data->meta_og_image && file_exists($path . $data->meta_og_image)) {
                unlink($path . $data->meta_og_image);
            }

            Image::make($metaOgImage)
                ->resize(1200, 630, function ($constraint) {
                    $constraint->aspectRatio();
                    $constraint->upsize();
                })
                ->encode('webp', 80)
                ->save($path . $metaOgImageName);

            $data->meta_og_image = $metaOgImageName;
        }

        // Update all fields
        $data->company_name = $request->company_name;
        $data->business_name = $request->business_name;
        $data->email1 = $request->email1;
        $data->email2 = $request->email2;
        $data->phone1 = $request->phone1;
        $data->phone2 = $request->phone2;
        $data->phone3 = $request->phone3;
        $data->phone4 = $request->phone4;
        $data->whatsapp = $request->whatsapp;
        $data->address1 = $request->address1;
        $data->address2 = $request->address2;
        $data->website = $request->website;
        $data->facebook = $request->facebook;
        $data->instagram = $request->instagram;
        $data->twitter = $request->twitter;
        $data->linkedin = $request->linkedin;
        $data->youtube = $request->youtube;
        $data->tiktok = $request->tiktok;
        $data->tawkto = $request->tawkto;
        $data->vat_percent = $request->vat_percent;
        $data->appstore_link = $request->appstore_link;
        $data->google_play_link = $request->google_play_link;
        $data->currency = $request->currency;
        $data->google_map = $request->google_map;
        $data->company_reg_number = $request->company_reg_number;
        $data->vat_number = $request->vat_number;
        $data->opening_time = $request->opening_time;
        $data->account_number = $request->account_number;
        $data->sort_code = $request->sort_code;
        $data->bank = $request->bank;

        // Content fields with Summernote
        $data->about_us = $request->about_us;
        $data->header_content = $request->header_content;
        $data->footer_content = $request->footer_content;
        $data->copyright = $request->copyright;
        $data->privacy_policy = $request->privacy_policy;
        $data->terms_and_conditions = $request->terms_and_conditions;
        $data->refund_policy = $request->refund_policy;
        $data->bank_info = $request->bank_info;

        // SEO & Analytics fields
        $data->google_site_verification = $request->google_site_verification;
        $data->google_analytics = $request->google_analytics;
        $data->google_tag_manager = $request->google_tag_manager;
        $data->facebook_pixel_id = $request->facebook_pixel_id;
        $data->meta_title = $request->meta_title;
        $data->meta_description = $request->meta_description;
        $data->meta_keywords = $request->meta_keywords;
        $data->meta_og_title = $request->meta_og_title;

        $data->save();

        return response()->json(['success' => true, 'message' => 'Company details updated successfully.']);
    }
}