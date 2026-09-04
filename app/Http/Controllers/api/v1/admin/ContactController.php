<?php

namespace App\Http\Controllers\api\v1\admin;

use App\Events\SettingsChanged;
use App\Http\Controllers\Controller;
use App\Http\Requests\ContactStoreRequest;
use App\Models\Contact;
use DB;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    public function updateSortOrder(Request $request)
    {
        $request->validate([
            'items' => 'required|array',
            'items.*.id' => 'required|exists:contacts,id',
            'items.*.sort_order' => 'required|integer|min:1'
        ]);

        DB::transaction(function () use ($request) {
            foreach ($request->items as $item) {
                Contact::where('id', $item['id'])
                    ->update(['sort_order' => $item['sort_order']]);
            }
        });

        event(new SettingsChanged());

        return response()->json([
            'message' => 'ترتیب با موفقیت به‌روزرسانی شد',
            'success' => true
        ]);
    }

    public function store(ContactStoreRequest $request)
    {
        $validated = $request->validated();

        $validated['sort_order'] = Contact::max('sort_order') + 1;

        $contact = Contact::create($validated);

        return response()->json([
            'message' => 'تلفن جدید با موفقیت ایجاد شد.',
            'data' => $contact
        ], 201);
    }

    public function update(ContactStoreRequest $request, Contact $contact)
    {
        $validated = $request->validated();

        $contact->update($validated);

        return response()->json([
            'message' => 'تلفن با موفقیت بروزرسانی شد.',
            'data' => $contact
        ]);
    }


    public function destroy(Contact $contact)
    {
        $contact->delete();

        return response()->json([
            'message' => 'تلفن با موفقیت حذف شد.',
        ]);
    }
}
