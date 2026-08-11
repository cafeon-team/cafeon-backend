<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CustomerInquiry;
use App\Models\Faq;
use App\Models\MembershipStampEvent;
use App\Models\Referral;
use App\Models\Store;
use App\Models\StoreFavorite;
use App\Models\UserPreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FrontendFeatureController extends Controller
{
    public function favorites(Request $request): JsonResponse
    {
        return response()->json(StoreFavorite::with('store.images')->where('user_id', $request->user()->id)->latest()->get());
    }
    public function favorite(Request $request, Store $store): JsonResponse
    {
        $favorite = StoreFavorite::firstOrCreate(['user_id'=>$request->user()->id,'store_id'=>$store->id]);
        return response()->json($favorite->load('store'), 201);
    }
    public function unfavorite(Request $request, Store $store): JsonResponse
    {
        StoreFavorite::where('user_id',$request->user()->id)->where('store_id',$store->id)->delete();
        return response()->json(status:204);
    }
    public function preferences(Request $request): JsonResponse
    {
        return response()->json(UserPreference::firstOrCreate(['user_id'=>$request->user()->id]));
    }
    public function updatePreferences(Request $request): JsonResponse
    {
        $data=$request->validate(['order_notifications'=>'sometimes|boolean','location_enabled'=>'sometimes|boolean','marketing_notifications'=>'sometimes|boolean','latitude'=>'nullable|numeric|between:-90,90','longitude'=>'nullable|numeric|between:-180,180','preferred_tags'=>'sometimes|array|max:10','preferred_tags.*'=>'string|max:50']);
        $preference=UserPreference::updateOrCreate(['user_id'=>$request->user()->id],$data);
        return response()->json($preference);
    }
    public function faqs(): JsonResponse
    {
        return response()->json(Faq::where('is_active',true)->orderBy('sort_order')->get());
    }
    public function storeFaq(Request $request): JsonResponse
    {
        abort_unless($request->user()->role==='ADMIN',403); $data=$request->validate(['category'=>'sometimes|string|max:50','question'=>'required|string|max:255','answer'=>'required|string|max:5000','sort_order'=>'sometimes|integer|min:0','is_active'=>'sometimes|boolean']);
        return response()->json(Faq::create($data),201);
    }
    public function updateFaq(Request $request, Faq $faq): JsonResponse
    {
        abort_unless($request->user()->role==='ADMIN',403); $faq->update($request->validate(['category'=>'sometimes|string|max:50','question'=>'sometimes|string|max:255','answer'=>'sometimes|string|max:5000','sort_order'=>'sometimes|integer|min:0','is_active'=>'sometimes|boolean'])); return response()->json($faq);
    }
    public function deleteFaq(Request $request, Faq $faq): JsonResponse
    {
        abort_unless($request->user()->role==='ADMIN',403); $faq->delete(); return response()->json(status:204);
    }
    public function inquiries(Request $request): JsonResponse
    {
        return response()->json(CustomerInquiry::where('user_id',$request->user()->id)->latest()->paginate(20));
    }
    public function storeInquiry(Request $request): JsonResponse
    {
        $data=$request->validate(['category'=>'required|string|max:50','title'=>'required|string|max:255','content'=>'required|string|max:5000']);
        return response()->json(CustomerInquiry::create($data+['user_id'=>$request->user()->id]),201);
    }
    public function showInquiry(Request $request, CustomerInquiry $inquiry): JsonResponse
    {
        abort_unless($inquiry->user_id===$request->user()->id||$request->user()->role==='ADMIN',403);
        return response()->json($inquiry);
    }
    public function answerInquiry(Request $request, CustomerInquiry $inquiry): JsonResponse
    {
        abort_unless($request->user()->role==='ADMIN',403); $data=$request->validate(['answer'=>'required|string|max:5000','status'=>'sometimes|in:ANSWERED,CLOSED']);
        $inquiry->update(['answer'=>$data['answer'],'status'=>$data['status']??'ANSWERED','answered_by'=>$request->user()->id,'answered_at'=>now()]); return response()->json($inquiry);
    }
    public function recommendations(Request $request): JsonResponse
    {
        $data=$request->validate(['tags'=>'nullable|array|max:10','tags.*'=>'string|max:50','limit'=>'nullable|integer|min:1|max:20']);
        $tags=$data['tags']??UserPreference::where('user_id',$request->user()?->id)->first()?->preferred_tags??[];
        $stores=Store::query()->where('is_active',true)->with(['images','tags'])->withAvg('reviews','rating')
            ->when($tags,fn($q)=>$q->whereHas('tags',fn($t)=>$t->whereIn('slug',$tags)->orWhereIn('name',$tags)))
            ->withCount(['reviews','seats'])->limit($data['limit']??8)->get();
        return response()->json(['criteria'=>['tags'=>$tags],'stores'=>$stores]);
    }
    public function membershipSummary(Request $request): JsonResponse
    {
        $points=(int)$request->user()->customerStoreAccounts()->sum('point_balance');
        $stamps=(int)MembershipStampEvent::where('user_id',$request->user()->id)->sum('amount');
        $grade=match(true){$points>=30000=>'PLATINUM',$points>=15000=>'GOLD',$points>=5000=>'SILVER',default=>'BRONZE'};
        return response()->json(['grade'=>$grade,'total_points'=>$points,'stamps'=>$stamps%10,'completed_stamp_cards'=>intdiv(max(0,$stamps),10),'next_grade_points'=>match($grade){'BRONZE'=>5000,'SILVER'=>15000,'GOLD'=>30000,default=>null}]);
    }
    public function referralCode(Request $request): JsonResponse
    {
        $referral=Referral::firstOrCreate(['inviter_id'=>$request->user()->id,'status'=>'AVAILABLE'],['code'=>Str::upper(Str::random(10))]);
        return response()->json($referral);
    }
    public function claimReferral(Request $request): JsonResponse
    {
        $data=$request->validate(['code'=>'required|string','store_id'=>'required|exists:stores,id']);
        $referral=DB::transaction(function()use($request,$data){$r=Referral::where('code',Str::upper($data['code']))->lockForUpdate()->firstOrFail(); abort_if($r->inviter_id===$request->user()->id||$r->status!=='AVAILABLE'||Referral::where('invitee_id',$request->user()->id)->exists(),422,'사용할 수 없는 추천 코드입니다.'); $r->update(['invitee_id'=>$request->user()->id,'status'=>'COMPLETED','completed_at'=>now()]); foreach([$r->inviter_id,$request->user()->id] as $userId){$account=\App\Models\CustomerStoreAccount::firstOrCreate(['user_id'=>$userId,'store_id'=>$data['store_id']],['point_balance'=>0,'total_earned_points'=>0,'visit_count'=>0,'purchase_count'=>0,'total_purchase_amount'=>0]);$account->increment('point_balance',$r->reward_points);$account->increment('total_earned_points',$r->reward_points);} return $r;});
        return response()->json(['message'=>'추천 포인트가 지급되었습니다.','referral'=>$referral]);
    }
}
