<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CompanyBranch;
use App\Models\FounderGallery;
use App\Models\SiteSetting;
use App\Models\StaffMember;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

class SiteContentAdminController extends Controller
{
    private const SETTINGS = [
        'company_name' => ['label' => 'Company name', 'rules' => ['required', 'string', 'max:120']],
        'tagline' => ['label' => 'Tagline', 'rules' => ['nullable', 'string', 'max:255']],
        'primary_phone' => ['label' => 'Primary phone', 'rules' => ['nullable', 'string', 'max:50']],
        'secondary_phone' => ['label' => 'Secondary phone', 'rules' => ['nullable', 'string', 'max:50']],
        'whatsapp_phone' => ['label' => 'WhatsApp phone', 'rules' => ['nullable', 'string', 'max:50']],
        'email' => ['label' => 'Contact email', 'rules' => ['nullable', 'email', 'max:255']],
        'homepage_hero_title' => ['label' => 'Homepage hero title', 'rules' => ['nullable', 'string', 'max:180']],
        'homepage_hero_subtitle' => ['label' => 'Homepage hero subtitle', 'rules' => ['nullable', 'string', 'max:500']],
        'founder_name' => ['label' => 'Founder name', 'rules' => ['nullable', 'string', 'max:120']],
        'founder_role' => ['label' => 'Founder role', 'rules' => ['nullable', 'string', 'max:120']],
        'founder_short_bio' => ['label' => 'Founder short biography', 'rules' => ['nullable', 'string', 'max:2000']],
        'founder_story' => ['label' => 'Founder story', 'rules' => ['nullable', 'string', 'max:10000']],
        'founder_quote' => ['label' => 'Founder quote', 'rules' => ['nullable', 'string', 'max:1000']],
        'facebook_url' => ['label' => 'Facebook URL', 'rules' => ['nullable', 'url', 'max:255']],
        'instagram_url' => ['label' => 'Instagram URL', 'rules' => ['nullable', 'url', 'max:255']],
        'twitter_url' => ['label' => 'X / Twitter URL', 'rules' => ['nullable', 'url', 'max:255']],
        'linkedin_url' => ['label' => 'LinkedIn URL', 'rules' => ['nullable', 'url', 'max:255']],
        'tiktok_url' => ['label' => 'TikTok URL', 'rules' => ['nullable', 'url', 'max:255']],
    ];

    public function settings(): View
    {
        $settings = SiteSetting::query()->pluck('setting_value', 'setting_key');
        $branches = CompanyBranch::query()->orderBy('sort_order')->orderBy('branch_name')->get();

        return view('admin.site.settings', compact('settings', 'branches'));
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $rules = collect(self::SETTINGS)->mapWithKeys(fn (array $setting, string $key): array => [$key => $setting['rules']])->all();
        $rules += [
            'logo_path_upload' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'home_hero_image_upload' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'founder_thumbnail_upload' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'founder_hero_image_upload' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'staff_hero_image_upload' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'contact_hero_image_upload' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'blog_hero_image_upload' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
        $data = $request->validate($rules);

        foreach (array_keys(self::SETTINGS) as $key) {
            if (array_key_exists($key, $data)) {
                SiteSetting::updateOrCreate(['setting_key' => $key], ['setting_value' => trim((string) $data[$key])]);
            }
        }
        foreach ([
            'logo_path_upload' => 'logo_path',
            'home_hero_image_upload' => 'home_hero_image',
            'founder_thumbnail_upload' => 'founder_thumbnail',
            'founder_hero_image_upload' => 'founder_hero_image',
            'staff_hero_image_upload' => 'staff_hero_image',
            'contact_hero_image_upload' => 'contact_hero_image',
            'blog_hero_image_upload' => 'blog_hero_image',
        ] as $input => $key) {
            if ($request->file($input)) {
                $stored = $request->file($input)->store('branding', 'public');
                if (! $stored) {
                    throw new RuntimeException("The {$key} image could not be stored.");
                }
                SiteSetting::updateOrCreate(['setting_key' => $key], ['setting_value' => $stored]);
            }
        }

        return back()->with('status', 'Site settings saved.');
    }

    public function storeBranch(Request $request): RedirectResponse
    {
        CompanyBranch::create($this->validatedBranch($request));

        return back()->with('status', 'Branch added.');
    }

    public function updateBranch(Request $request, CompanyBranch $branch): RedirectResponse
    {
        $branch->update($this->validatedBranch($request));

        return back()->with('status', 'Branch updated.');
    }

    public function deactivateBranch(CompanyBranch $branch): RedirectResponse
    {
        $branch->update(['is_active' => false]);

        return back()->with('status', 'Branch hidden from public pages.');
    }

    public function staff(): View
    {
        $staff = StaffMember::query()->with('branch')->orderBy('sort_order')->orderBy('name')->paginate(20);
        $branches = CompanyBranch::query()->where('is_active', true)->orderBy('branch_name')->get();

        return view('admin.site.staff-index', compact('staff', 'branches'));
    }

    public function storeStaff(Request $request): RedirectResponse
    {
        $data = $this->validatedStaff($request);
        $data['thumbnail'] = $this->storeImage($request, 'thumbnail_upload', 'staff') ?? null;
        StaffMember::create($data);

        return back()->with('status', 'Staff member added.');
    }

    public function updateStaff(Request $request, StaffMember $staffMember): RedirectResponse
    {
        $data = $this->validatedStaff($request);
        $stored = $this->storeImage($request, 'thumbnail_upload', 'staff');
        if ($stored) {
            $data['thumbnail'] = $stored;
        }
        $staffMember->update($data);

        return back()->with('status', 'Staff member updated.');
    }

    public function deactivateStaff(StaffMember $staffMember): RedirectResponse
    {
        $staffMember->update(['is_active' => false]);

        return back()->with('status', 'Staff member hidden from public pages.');
    }

    public function gallery(): View
    {
        $items = FounderGallery::query()->orderBy('sort_order')->orderBy('id')->paginate(20);

        return view('admin.site.gallery-index', compact('items'));
    }

    public function storeGalleryItem(Request $request): RedirectResponse
    {
        $data = $this->validatedGalleryItem($request, true);
        $data['image_path'] = $this->storeImage($request, 'image_upload', 'founder');
        FounderGallery::create($data);

        return back()->with('status', 'Gallery item added.');
    }

    public function updateGalleryItem(Request $request, FounderGallery $founderGallery): RedirectResponse
    {
        $data = $this->validatedGalleryItem($request, false);
        $stored = $this->storeImage($request, 'image_upload', 'founder');
        if ($stored) {
            $data['image_path'] = $stored;
        }
        $founderGallery->update($data);

        return back()->with('status', 'Gallery item updated.');
    }

    public function deactivateGalleryItem(FounderGallery $founderGallery): RedirectResponse
    {
        $founderGallery->update(['is_active' => false]);

        return back()->with('status', 'Gallery item hidden from public pages.');
    }

    private function validatedBranch(Request $request): array
    {
        return $request->validate([
            'branch_name' => ['required', 'string', 'max:255'],
            'phone_primary' => ['nullable', 'string', 'max:50'],
            'phone_secondary' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:2000'],
            'google_maps_url' => ['nullable', 'url', 'max:2000'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:100000'],
            'is_active' => ['sometimes', 'boolean'],
        ]) + ['is_active' => $request->boolean('is_active')];
    }

    private function validatedStaff(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'designation' => ['required', 'string', 'max:255'],
            'branch_id' => ['nullable', 'integer', Rule::exists('company_branches', 'id')],
            'email' => ['nullable', 'email', 'max:255'],
            'contact' => ['nullable', 'string', 'max:50'],
            'bio' => ['nullable', 'string', 'max:3000'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:100000'],
            'is_active' => ['sometimes', 'boolean'],
            'thumbnail_upload' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);
        unset($data['thumbnail_upload']);
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }

    private function validatedGalleryItem(Request $request, bool $creating): array
    {
        $data = $request->validate([
            'item_title' => ['required', 'string', 'max:255'],
            'item_type' => ['required', 'string', 'max:40'],
            'item_description' => ['nullable', 'string', 'max:2000'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:100000'],
            'is_active' => ['sometimes', 'boolean'],
            'image_upload' => [$creating ? 'required' : 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);
        unset($data['image_upload']);
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }

    private function storeImage(Request $request, string $input, string $directory): ?string
    {
        if (! $request->file($input)) {
            return null;
        }
        $stored = $request->file($input)->store($directory, 'public');
        if (! $stored) {
            throw new RuntimeException('The uploaded image could not be stored.');
        }

        return $stored;
    }
}
