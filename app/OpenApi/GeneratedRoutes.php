<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

final class GeneratedRoutes
{
    #[OA\Post(
        path: '/api/admin/faqs',
        operationId: 'post_api_admin_faqs_5fc83a',
        summary: 'storeFaq (admin/faqs)',
        tags: ['Admin'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: '성공'), new OA\Response(response: 401, description: '인증 필요'), new OA\Response(response: 403, description: '권한 없음'), new OA\Response(response: 422, description: '입력값 또는 상태 검증 실패')]
    )]
    public function post_api_admin_faqs_5fc83a(): void {}

    #[OA\Put(
        path: '/api/admin/faqs/{faq}',
        operationId: 'put_api_admin_faqs_faq_a8d2bf',
        summary: 'updateFaq (admin/faqs/{faq})',
        tags: ['Admin'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'faq', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: '성공'), new OA\Response(response: 401, description: '인증 필요'), new OA\Response(response: 403, description: '권한 없음'), new OA\Response(response: 422, description: '입력값 또는 상태 검증 실패')]
    )]
    public function put_api_admin_faqs_faq_a8d2bf(): void {}

    #[OA\Delete(
        path: '/api/admin/faqs/{faq}',
        operationId: 'delete_api_admin_faqs_faq_356df5',
        summary: 'deleteFaq (admin/faqs/{faq})',
        tags: ['Admin'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'faq', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 204, description: '성공'), new OA\Response(response: 401, description: '인증 필요'), new OA\Response(response: 403, description: '권한 없음')]
    )]
    public function delete_api_admin_faqs_faq_356df5(): void {}

    #[OA\Patch(
        path: '/api/admin/inquiries/{inquiry}/answer',
        operationId: 'patch_api_admin_inquiries_inquiry_answer_33117a',
        summary: 'answerInquiry (admin/inquiries/{inquiry}/answer)',
        tags: ['Admin'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'inquiry', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: '성공'), new OA\Response(response: 401, description: '인증 필요'), new OA\Response(response: 403, description: '권한 없음'), new OA\Response(response: 422, description: '입력값 또는 상태 검증 실패')]
    )]
    public function patch_api_admin_inquiries_inquiry_answer_33117a(): void {}

    #[OA\Post(
        path: '/api/admin/subscriptions/{subscription}/activate',
        operationId: 'post_api_admin_subscriptions_subscription_activate_3cfbc2',
        summary: '구독 결제 확인 및 활성화 (admin/subscriptions/{subscription}/activate)',
        tags: ['Plans'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'subscription', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['provider_transaction_id'], properties: [new OA\Property(property: 'provider_transaction_id', type: 'string')])),
        responses: [new OA\Response(response: 200, description: '성공'), new OA\Response(response: 401, description: '인증 필요'), new OA\Response(response: 403, description: '권한 없음'), new OA\Response(response: 422, description: '입력값 또는 상태 검증 실패')]
    )]
    public function post_api_admin_subscriptions_subscription_activate_3cfbc2(): void {}

    #[OA\Post(
        path: '/api/auth/login',
        operationId: 'post_api_auth_login_3d54ae',
        summary: '로그인 (auth/login)',
        tags: ['Authentication'],
        responses: [new OA\Response(response: 200, description: '성공'), new OA\Response(response: 422, description: '입력값 또는 상태 검증 실패')]
    )]
    public function post_api_auth_login_3d54ae(): void {}

    #[OA\Post(
        path: '/api/auth/owner/signup',
        operationId: 'post_api_auth_owner_signup_8c62a0',
        summary: '점주 회원가입 (auth/owner/signup)',
        tags: ['Authentication'],
        responses: [new OA\Response(response: 200, description: '성공'), new OA\Response(response: 422, description: '입력값 또는 상태 검증 실패')]
    )]
    public function post_api_auth_owner_signup_8c62a0(): void {}

    #[OA\Post(
        path: '/api/auth/signup',
        operationId: 'post_api_auth_signup_3f40e6',
        summary: '회원가입 (auth/signup)',
        tags: ['Authentication'],
        responses: [new OA\Response(response: 200, description: '성공'), new OA\Response(response: 422, description: '입력값 또는 상태 검증 실패')]
    )]
    public function post_api_auth_signup_3f40e6(): void {}

    #[OA\Post(
        path: '/api/auth/social/exchange',
        operationId: 'post_api_auth_social_exchange_06530e',
        summary: 'exchange (auth/social/exchange)',
        tags: ['Authentication'],
        responses: [new OA\Response(response: 200, description: '성공'), new OA\Response(response: 422, description: '입력값 또는 상태 검증 실패')]
    )]
    public function post_api_auth_social_exchange_06530e(): void {}

    #[OA\Put(
        path: '/api/comments/{comment}',
        operationId: 'put_api_comments_comment_da482d',
        summary: '수정 (comments/{comment})',
        tags: ['Blog'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'comment', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: '성공'), new OA\Response(response: 401, description: '인증 필요'), new OA\Response(response: 403, description: '권한 없음'), new OA\Response(response: 422, description: '입력값 또는 상태 검증 실패')]
    )]
    public function put_api_comments_comment_da482d(): void {}

    #[OA\Delete(
        path: '/api/comments/{comment}',
        operationId: 'delete_api_comments_comment_f15b76',
        summary: '삭제 (comments/{comment})',
        tags: ['Blog'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'comment', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 204, description: '성공'), new OA\Response(response: 401, description: '인증 필요'), new OA\Response(response: 403, description: '권한 없음')]
    )]
    public function delete_api_comments_comment_f15b76(): void {}

    #[OA\Patch(
        path: '/api/comments/{comment}/status',
        operationId: 'patch_api_comments_comment_status_e43771',
        summary: '상태 변경 (comments/{comment}/status)',
        tags: ['Blog'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'comment', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: '성공'), new OA\Response(response: 401, description: '인증 필요'), new OA\Response(response: 403, description: '권한 없음'), new OA\Response(response: 422, description: '입력값 또는 상태 검증 실패')]
    )]
    public function patch_api_comments_comment_status_e43771(): void {}

    #[OA\Get(
        path: '/api/directions',
        operationId: 'get_api_directions_0fe584',
        summary: '상세 조회 (directions)',
        tags: ['Stores'],
        responses: [new OA\Response(response: 200, description: '성공')]
    )]
    public function get_api_directions_0fe584(): void {}

    #[OA\Get(
        path: '/api/faqs',
        operationId: 'get_api_faqs_02228f',
        summary: 'faqs (faqs)',
        tags: ['General'],
        responses: [new OA\Response(response: 200, description: '성공')]
    )]
    public function get_api_faqs_02228f(): void {}

    #[OA\Post(
        path: '/api/login',
        operationId: 'post_api_login_9d4135',
        summary: '로그인 (login)',
        tags: ['Authentication'],
        responses: [new OA\Response(response: 200, description: '성공'), new OA\Response(response: 422, description: '입력값 또는 상태 검증 실패')]
    )]
    public function post_api_login_9d4135(): void {}

    #[OA\Post(
        path: '/api/logout',
        operationId: 'post_api_logout_cab0d8',
        summary: '로그아웃 (logout)',
        tags: ['Authentication'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: '성공'), new OA\Response(response: 401, description: '인증 필요'), new OA\Response(response: 403, description: '권한 없음'), new OA\Response(response: 422, description: '입력값 또는 상태 검증 실패')]
    )]
    public function post_api_logout_cab0d8(): void {}

    #[OA\Get(
        path: '/api/map/stores',
        operationId: 'get_api_map_stores_c33b3d',
        summary: '목록 조회 (map/stores)',
        tags: ['Stores'],
        responses: [new OA\Response(response: 200, description: '성공')]
    )]
    public function get_api_map_stores_c33b3d(): void {}

    #[OA\Get(
        path: '/api/me',
        operationId: 'get_api_me_0f9bb8',
        summary: '내 정보 조회 (me)',
        tags: ['Authentication'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: '성공'), new OA\Response(response: 401, description: '인증 필요'), new OA\Response(response: 403, description: '권한 없음')]
    )]
    public function get_api_me_0f9bb8(): void {}

    #[OA\Get(
        path: '/api/menus/{menu}',
        operationId: 'get_api_menus_menu_fbc702',
        summary: '상세 조회 (menus/{menu})',
        tags: ['Stores'],
        parameters: [new OA\Parameter(name: 'menu', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: '성공')]
    )]
    public function get_api_menus_menu_fbc702(): void {}

    #[OA\Get(
        path: '/api/notifications',
        operationId: 'get_api_notifications_abb9e2',
        summary: '목록 조회 (notifications)',
        tags: ['Notifications'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'unread_only', in: 'query', schema: new OA\Schema(type: 'boolean')), new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100))],
        responses: [new OA\Response(response: 200, description: '성공'), new OA\Response(response: 401, description: '인증 필요'), new OA\Response(response: 403, description: '권한 없음')]
    )]
    public function get_api_notifications_abb9e2(): void {}

    #[OA\Delete(
        path: '/api/notifications',
        operationId: 'delete_api_notifications_427599',
        summary: '전체 알림 삭제 (notifications)',
        tags: ['Notifications'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 204, description: '성공'), new OA\Response(response: 401, description: '인증 필요'), new OA\Response(response: 403, description: '권한 없음')]
    )]
    public function delete_api_notifications_427599(): void {}

    #[OA\Patch(
        path: '/api/notifications/read-all',
        operationId: 'patch_api_notifications_read_all_47f4bc',
        summary: '전체 알림 읽음 처리 (notifications/read-all)',
        tags: ['Notifications'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: '성공'), new OA\Response(response: 401, description: '인증 필요'), new OA\Response(response: 403, description: '권한 없음'), new OA\Response(response: 422, description: '입력값 또는 상태 검증 실패')]
    )]
    public function patch_api_notifications_read_all_47f4bc(): void {}

    #[OA\Delete(
        path: '/api/notifications/{notification}',
        operationId: 'delete_api_notifications_notification_2d1e9d',
        summary: '삭제 (notifications/{notification})',
        tags: ['Notifications'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'notification', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 204, description: '성공'), new OA\Response(response: 401, description: '인증 필요'), new OA\Response(response: 403, description: '권한 없음')]
    )]
    public function delete_api_notifications_notification_2d1e9d(): void {}

    #[OA\Patch(
        path: '/api/notifications/{notification}/read',
        operationId: 'patch_api_notifications_notification_read_c04623',
        summary: '알림 읽음 처리 (notifications/{notification}/read)',
        tags: ['Notifications'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'notification', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: '성공'), new OA\Response(response: 401, description: '인증 필요'), new OA\Response(response: 403, description: '권한 없음'), new OA\Response(response: 422, description: '입력값 또는 상태 검증 실패')]
    )]
    public function patch_api_notifications_notification_read_c04623(): void {}

    #[OA\Post(
        path: '/api/orders',
        operationId: 'post_api_orders_7f6c9e',
        summary: '등록 (orders)',
        tags: ['Orders & Payments'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: '성공'), new OA\Response(response: 401, description: '인증 필요'), new OA\Response(response: 403, description: '권한 없음'), new OA\Response(response: 422, description: '입력값 또는 상태 검증 실패')]
    )]
    public function post_api_orders_7f6c9e(): void {}

    #[OA\Put(
        path: '/api/owner/inventory/{inventory}',
        operationId: 'put_api_owner_inventory_inventory_6f5468',
        summary: '수정 (owner/inventory/{inventory})',
        tags: ['Owner Inventory'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'inventory', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: '성공'), new OA\Response(response: 401, description: '인증 필요'), new OA\Response(response: 403, description: '권한 없음'), new OA\Response(response: 422, description: '입력값 또는 상태 검증 실패')]
    )]
    public function put_api_owner_inventory_inventory_6f5468(): void {}

    #[OA\Delete(
        path: '/api/owner/inventory/{inventory}',
        operationId: 'delete_api_owner_inventory_inventory_460b53',
        summary: '삭제 (owner/inventory/{inventory})',
        tags: ['Owner Inventory'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'inventory', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 204, description: '성공'), new OA\Response(response: 401, description: '인증 필요'), new OA\Response(response: 403, description: '권한 없음')]
    )]
    public function delete_api_owner_inventory_inventory_460b53(): void {}

    #[OA\Post(
        path: '/api/owner/inventory/{inventory}/transactions',
        operationId: 'post_api_owner_inventory_inventory_transactions_83a380',
        summary: '재고 수량 변경 (owner/inventory/{inventory}/transactions)',
        tags: ['Owner Inventory'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'inventory', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['type'], properties: [new OA\Property(property: 'type', type: 'string', enum: ['STOCK_IN', 'STOCK_OUT', 'ADJUSTMENT', 'RETURN', 'WASTE']), new OA\Property(property: 'quantity', type: 'number', example: 3), new OA\Property(property: 'quantity_after', type: 'number', nullable: true), new OA\Property(property: 'reason', type: 'string', nullable: true)])),
        responses: [new OA\Response(response: 201, description: '성공'), new OA\Response(response: 401, description: '인증 필요'), new OA\Response(response: 403, description: '권한 없음'), new OA\Response(response: 422, description: '입력값 또는 상태 검증 실패')]
    )]
    public function post_api_owner_inventory_inventory_transactions_83a380(): void {}

    #[OA\Put(
        path: '/api/owner/menu-categories/{category}',
        operationId: 'put_api_owner_menu_categories_category_7b5cba',
        summary: 'updateCategory (owner/menu-categories/{category})',
        tags: ['General'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'category', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: '성공'), new OA\Response(response: 401, description: '인증 필요'), new OA\Response(response: 403, description: '권한 없음'), new OA\Response(response: 422, description: '입력값 또는 상태 검증 실패')]
    )]
    public function put_api_owner_menu_categories_category_7b5cba(): void {}

    #[OA\Delete(
        path: '/api/owner/menu-categories/{category}',
        operationId: 'delete_api_owner_menu_categories_category_bdf0c7',
        summary: 'destroyCategory (owner/menu-categories/{category})',
        tags: ['General'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'category', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 204, description: '성공'), new OA\Response(response: 401, description: '인증 필요'), new OA\Response(response: 403, description: '권한 없음')]
    )]
    public function delete_api_owner_menu_categories_category_bdf0c7(): void {}

    #[OA\Put(
        path: '/api/owner/menus/{menu}',
        operationId: 'put_api_owner_menus_menu_10b463',
        summary: '수정 (owner/menus/{menu})',
        tags: ['Stores'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'menu', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: '성공'), new OA\Response(response: 401, description: '인증 필요'), new OA\Response(response: 403, description: '권한 없음'), new OA\Response(response: 422, description: '입력값 또는 상태 검증 실패')]
    )]
    public function put_api_owner_menus_menu_10b463(): void {}

    #[OA\Delete(
        path: '/api/owner/menus/{menu}',
        operationId: 'delete_api_owner_menus_menu_6d63e9',
        summary: '삭제 (owner/menus/{menu})',
        tags: ['Stores'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'menu', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 204, description: '성공'), new OA\Response(response: 401, description: '인증 필요'), new OA\Response(response: 403, description: '권한 없음')]
    )]
    public function delete_api_owner_menus_menu_6d63e9(): void {}

    #[OA\Patch(
        path: '/api/owner/menus/{menu}/availability',
        operationId: 'patch_api_owner_menus_menu_availability_7877f4',
        summary: 'updateAvailability (owner/menus/{menu}/availability)',
        tags: ['Stores'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'menu', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: '성공'), new OA\Response(response: 401, description: '인증 필요'), new OA\Response(response: 403, description: '권한 없음'), new OA\Response(response: 422, description: '입력값 또는 상태 검증 실패')]
    )]
    public function patch_api_owner_menus_menu_availability_7877f4(): void {}

    #[OA\Patch(
        path: '/api/owner/orders/{order}/status',
        operationId: 'patch_api_owner_orders_order_status_c86692',
        summary: '상태 변경 (owner/orders/{order}/status)',
        tags: ['Orders & Payments'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'order', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: '성공'), new OA\Response(response: 401, description: '인증 필요'), new OA\Response(response: 403, description: '권한 없음'), new OA\Response(response: 422, description: '입력값 또는 상태 검증 실패')]
    )]
    public function patch_api_owner_orders_order_status_c86692(): void {}

    #[OA\Put(
        path: '/api/owner/review-replies/{reply}',
        operationId: 'put_api_owner_review_replies_reply_fbd983',
        summary: '수정 (owner/review-replies/{reply})',
        tags: ['General'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'reply', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: '성공'), new OA\Response(response: 401, description: '인증 필요'), new OA\Response(response: 403, description: '권한 없음'), new OA\Response(response: 422, description: '입력값 또는 상태 검증 실패')]
    )]
    public function put_api_owner_review_replies_reply_fbd983(): void {}

    #[OA\Delete(
        path: '/api/owner/review-replies/{reply}',
        operationId: 'delete_api_owner_review_replies_reply_1d06b8',
        summary: '삭제 (owner/review-replies/{reply})',
        tags: ['General'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'reply', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 204, description: '성공'), new OA\Response(response: 401, description: '인증 필요'), new OA\Response(response: 403, description: '권한 없음')]
    )]
    public function delete_api_owner_review_replies_reply_1d06b8(): void {}

    #[OA\Post(
        path: '/api/owner/reviews/{review}/reply',
        operationId: 'post_api_owner_reviews_review_reply_0478f5',
        summary: '등록 (owner/reviews/{review}/reply)',
        tags: ['Reviews'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'review', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: '성공'), new OA\Response(response: 401, description: '인증 필요'), new OA\Response(response: 403, description: '권한 없음'), new OA\Response(response: 422, description: '입력값 또는 상태 검증 실패')]
    )]
    public function post_api_owner_reviews_review_reply_0478f5(): void {}

    #[OA\Patch(
        path: '/api/owner/staff/{member}',
        operationId: 'patch_api_owner_staff_member_2b7316',
        summary: '수정 (owner/staff/{member})',
        tags: ['Owner Staff'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'member', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(properties: [new OA\Property(property: 'role', type: 'string', enum: ['MANAGER', 'STAFF']), new OA\Property(property: 'is_active', type: 'boolean')])),
        responses: [new OA\Response(response: 200, description: '성공'), new OA\Response(response: 401, description: '인증 필요'), new OA\Response(response: 403, description: '권한 없음'), new OA\Response(response: 422, description: '입력값 또는 상태 검증 실패')]
    )]
    public function patch_api_owner_staff_member_2b7316(): void {}

    #[OA\Delete(
        path: '/api/owner/staff/{member}',
        operationId: 'delete_api_owner_staff_member_33f0f0',
        summary: '삭제 (owner/staff/{member})',
        tags: ['Owner Staff'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'member', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 204, description: '성공'), new OA\Response(response: 401, description: '인증 필요'), new OA\Response(response: 403, description: '권한 없음')]
    )]
    public function delete_api_owner_staff_member_33f0f0(): void {}

    #[OA\Patch(
        path: '/api/owner/stores/{store}/availability',
        operationId: 'patch_api_owner_stores_store_availability_8c73e6',
        summary: 'updateMany (owner/stores/{store}/availability)',
        tags: ['Stores'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'store', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: '성공'), new OA\Response(response: 401, description: '인증 필요'), new OA\Response(response: 403, description: '권한 없음'), new OA\Response(response: 422, description: '입력값 또는 상태 검증 실패')]
    )]
    public function patch_api_owner_stores_store_availability_8c73e6(): void {}

    #[OA\Get(
        path: '/api/owner/stores/{store}/dashboard',
        operationId: 'get_api_owner_stores_store_dashboard_308620',
        summary: '상세 조회 (owner/stores/{store}/dashboard)',
        tags: ['Stores'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'store', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: '성공'), new OA\Response(response: 401, description: '인증 필요'), new OA\Response(response: 403, description: '권한 없음')]
    )]
    public function get_api_owner_stores_store_dashboard_308620(): void {}

    #[OA\Get(
        path: '/api/owner/stores/{store}/inventory',
        operationId: 'get_api_owner_stores_store_inventory_744e98',
        summary: '목록 조회 (owner/stores/{store}/inventory)',
        tags: ['Owner Inventory'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'store', in: 'path', required: true, schema: new OA\Schema(type: 'integer')), new OA\Parameter(name: 'low_stock', in: 'query', schema: new OA\Schema(type: 'boolean')), new OA\Parameter(name: 'keyword', in: 'query', schema: new OA\Schema(type: 'string'))],
        responses: [new OA\Response(response: 200, description: '성공'), new OA\Response(response: 401, description: '인증 필요'), new OA\Response(response: 403, description: '권한 없음')]
    )]
    public function get_api_owner_stores_store_inventory_744e98(): void {}

    #[OA\Post(
        path: '/api/owner/stores/{store}/inventory',
        operationId: 'post_api_owner_stores_store_inventory_447f34',
        summary: '등록 (owner/stores/{store}/inventory)',
        tags: ['Owner Inventory'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'store', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['ingredient_name', 'unit'], properties: [new OA\Property(property: 'ingredient_name', type: 'string', example: '원두'), new OA\Property(property: 'quantity', type: 'number', example: 10), new OA\Property(property: 'unit', type: 'string', example: 'kg'), new OA\Property(property: 'low_stock_threshold', type: 'number', example: 2)])),
        responses: [new OA\Response(response: 201, description: '성공'), new OA\Response(response: 401, description: '인증 필요'), new OA\Response(response: 403, description: '권한 없음'), new OA\Response(response: 422, description: '입력값 또는 상태 검증 실패')]
    )]
    public function post_api_owner_stores_store_inventory_447f34(): void {}

    #[OA\Get(
        path: '/api/owner/stores/{store}/inventory/transactions',
        operationId: 'get_api_owner_stores_store_inventory_transactions_301a90',
        summary: '재고 거래 이력 (owner/stores/{store}/inventory/transactions)',
        tags: ['Owner Inventory'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'store', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: '성공'), new OA\Response(response: 401, description: '인증 필요'), new OA\Response(response: 403, description: '권한 없음')]
    )]
    public function get_api_owner_stores_store_inventory_transactions_301a90(): void {}

    #[OA\Post(
        path: '/api/owner/stores/{store}/menu-categories',
        operationId: 'post_api_owner_stores_store_menu_categories_205fc4',
        summary: 'storeCategory (owner/stores/{store}/menu-categories)',
        tags: ['Stores'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'store', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: '성공'), new OA\Response(response: 401, description: '인증 필요'), new OA\Response(response: 403, description: '권한 없음'), new OA\Response(response: 422, description: '입력값 또는 상태 검증 실패')]
    )]
    public function post_api_owner_stores_store_menu_categories_205fc4(): void {}

    #[OA\Get(
        path: '/api/owner/stores/{store}/menus',
        operationId: 'get_api_owner_stores_store_menus_3b6750',
        summary: '목록 조회 (owner/stores/{store}/menus)',
        tags: ['Stores'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'store', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: '성공'), new OA\Response(response: 401, description: '인증 필요'), new OA\Response(response: 403, description: '권한 없음')]
    )]
    public function get_api_owner_stores_store_menus_3b6750(): void {}

    #[OA\Post(
        path: '/api/owner/stores/{store}/menus',
        operationId: 'post_api_owner_stores_store_menus_8efad0',
        summary: '등록 (owner/stores/{store}/menus)',
        tags: ['Stores'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'store', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: '성공'), new OA\Response(response: 401, description: '인증 필요'), new OA\Response(response: 403, description: '권한 없음'), new OA\Response(response: 422, description: '입력값 또는 상태 검증 실패')]
    )]
    public function post_api_owner_stores_store_menus_8efad0(): void {}

    #[OA\Get(
        path: '/api/owner/stores/{store}/orders',
        operationId: 'get_api_owner_stores_store_orders_3d026e',
        summary: '목록 조회 (owner/stores/{store}/orders)',
        tags: ['Orders & Payments'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'store', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: '성공'), new OA\Response(response: 401, description: '인증 필요'), new OA\Response(response: 403, description: '권한 없음')]
    )]
    public function get_api_owner_stores_store_orders_3d026e(): void {}

    #[OA\Get(
        path: '/api/owner/stores/{store}/sales',
        operationId: 'get_api_owner_stores_store_sales_6671b8',
        summary: '목록 조회 (owner/stores/{store}/sales)',
        tags: ['Owner Sales'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'store', in: 'path', required: true, schema: new OA\Schema(type: 'integer')), new OA\Parameter(name: 'from', in: 'query', schema: new OA\Schema(type: 'string', format: 'date')), new OA\Parameter(name: 'to', in: 'query', schema: new OA\Schema(type: 'string', format: 'date'))],
        responses: [new OA\Response(response: 200, description: '성공'), new OA\Response(response: 401, description: '인증 필요'), new OA\Response(response: 403, description: '권한 없음')]
    )]
    public function get_api_owner_stores_store_sales_6671b8(): void {}

    #[OA\Patch(
        path: '/api/owner/stores/{store}/seats/{seat}',
        operationId: 'patch_api_owner_stores_store_seats_seat_78f0f0',
        summary: '수정 (owner/stores/{store}/seats/{seat})',
        tags: ['Stores'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'store', in: 'path', required: true, schema: new OA\Schema(type: 'integer')), new OA\Parameter(name: 'seat', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: '성공'), new OA\Response(response: 401, description: '인증 필요'), new OA\Response(response: 403, description: '권한 없음'), new OA\Response(response: 422, description: '입력값 또는 상태 검증 실패')]
    )]
    public function patch_api_owner_stores_store_seats_seat_78f0f0(): void {}

    #[OA\Get(
        path: '/api/owner/stores/{store}/staff',
        operationId: 'get_api_owner_stores_store_staff_df9ccb',
        summary: '목록 조회 (owner/stores/{store}/staff)',
        tags: ['Owner Staff'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'store', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: '성공'), new OA\Response(response: 401, description: '인증 필요'), new OA\Response(response: 403, description: '권한 없음')]
    )]
    public function get_api_owner_stores_store_staff_df9ccb(): void {}

    #[OA\Post(
        path: '/api/owner/stores/{store}/staff',
        operationId: 'post_api_owner_stores_store_staff_efaf6a',
        summary: '등록 (owner/stores/{store}/staff)',
        tags: ['Owner Staff'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'store', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['email', 'role'], properties: [new OA\Property(property: 'email', type: 'string', format: 'email'), new OA\Property(property: 'role', type: 'string', enum: ['MANAGER', 'STAFF'])])),
        responses: [new OA\Response(response: 201, description: '성공'), new OA\Response(response: 401, description: '인증 필요'), new OA\Response(response: 403, description: '권한 없음'), new OA\Response(response: 422, description: '입력값 또는 상태 검증 실패')]
    )]
    public function post_api_owner_stores_store_staff_efaf6a(): void {}

    #[OA\Get(
        path: '/api/owner/stores/{store}/waitlists',
        operationId: 'get_api_owner_stores_store_waitlists_61823c',
        summary: 'ownerIndex (owner/stores/{store}/waitlists)',
        tags: ['Reservations & Waitlist'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'store', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: '성공'), new OA\Response(response: 401, description: '인증 필요'), new OA\Response(response: 403, description: '권한 없음')]
    )]
    public function get_api_owner_stores_store_waitlists_61823c(): void {}

    #[OA\Patch(
        path: '/api/owner/waitlists/{waitlist}/status',
        operationId: 'patch_api_owner_waitlists_waitlist_status_57179c',
        summary: '상태 변경 (owner/waitlists/{waitlist}/status)',
        tags: ['Reservations & Waitlist'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'waitlist', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: '성공'), new OA\Response(response: 401, description: '인증 필요'), new OA\Response(response: 403, description: '권한 없음'), new OA\Response(response: 422, description: '입력값 또는 상태 검증 실패')]
    )]
    public function patch_api_owner_waitlists_waitlist_status_57179c(): void {}

    #[OA\Post(
        path: '/api/payments/confirm',
        operationId: 'post_api_payments_confirm_f72c4e',
        summary: '결제 승인 (payments/confirm)',
        tags: ['Orders & Payments'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: '성공'), new OA\Response(response: 401, description: '인증 필요'), new OA\Response(response: 403, description: '권한 없음'), new OA\Response(response: 422, description: '입력값 또는 상태 검증 실패')]
    )]
    public function post_api_payments_confirm_f72c4e(): void {}

    #[OA\Get(
        path: '/api/payments/orders/{order}/checkout',
        operationId: 'get_api_payments_orders_order_checkout_71c222',
        summary: '결제 준비 (payments/orders/{order}/checkout)',
        tags: ['Orders & Payments'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'order', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: '성공'), new OA\Response(response: 401, description: '인증 필요'), new OA\Response(response: 403, description: '권한 없음')]
    )]
    public function get_api_payments_orders_order_checkout_71c222(): void {}

    #[OA\Post(
        path: '/api/payments/orders/{order}/refund',
        operationId: 'post_api_payments_orders_order_refund_0b5aaa',
        summary: '결제 환불 (payments/orders/{order}/refund)',
        tags: ['Orders & Payments'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'order', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: '성공'), new OA\Response(response: 401, description: '인증 필요'), new OA\Response(response: 403, description: '권한 없음'), new OA\Response(response: 422, description: '입력값 또는 상태 검증 실패')]
    )]
    public function post_api_payments_orders_order_refund_0b5aaa(): void {}

    #[OA\Get(
        path: '/api/plans',
        operationId: 'get_api_plans_2781b7',
        summary: '목록 조회 (plans)',
        tags: ['Plans'],
        responses: [new OA\Response(response: 200, description: '성공')]
    )]
    public function get_api_plans_2781b7(): void {}

    #[OA\Get(
        path: '/api/plans/billing-history',
        operationId: 'get_api_plans_billing_history_9b65d4',
        summary: '구독 결제 이력 (plans/billing-history)',
        tags: ['Plans'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: '성공'), new OA\Response(response: 401, description: '인증 필요'), new OA\Response(response: 403, description: '권한 없음')]
    )]
    public function get_api_plans_billing_history_9b65d4(): void {}

    #[OA\Post(
        path: '/api/plans/downgrade',
        operationId: 'post_api_plans_downgrade_7b16a1',
        summary: 'Basic 요금제로 변경 (plans/downgrade)',
        tags: ['Plans'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: '성공'), new OA\Response(response: 401, description: '인증 필요'), new OA\Response(response: 403, description: '권한 없음'), new OA\Response(response: 422, description: '입력값 또는 상태 검증 실패')]
    )]
    public function post_api_plans_downgrade_7b16a1(): void {}

    #[OA\Get(
        path: '/api/plans/me',
        operationId: 'get_api_plans_me_689917',
        summary: '내 정보 조회 (plans/me)',
        tags: ['Plans'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: '성공'), new OA\Response(response: 401, description: '인증 필요'), new OA\Response(response: 403, description: '권한 없음')]
    )]
    public function get_api_plans_me_689917(): void {}

    #[OA\Post(
        path: '/api/plans/subscribe',
        operationId: 'post_api_plans_subscribe_4fdc10',
        summary: '요금제 구독 신청 (plans/subscribe)',
        tags: ['Plans'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['plan_id', 'billing_cycle'], properties: [new OA\Property(property: 'plan_id', type: 'integer'), new OA\Property(property: 'billing_cycle', type: 'string', enum: ['MONTHLY', 'YEARLY'])])),
        responses: [new OA\Response(response: 201, description: '성공'), new OA\Response(response: 401, description: '인증 필요'), new OA\Response(response: 403, description: '권한 없음'), new OA\Response(response: 422, description: '입력값 또는 상태 검증 실패')]
    )]
    public function post_api_plans_subscribe_4fdc10(): void {}

    #[OA\Put(
        path: '/api/post-categories/{category}',
        operationId: 'put_api_post_categories_category_8620e8',
        summary: 'updateCategory (post-categories/{category})',
        tags: ['Blog'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'category', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: '성공'), new OA\Response(response: 401, description: '인증 필요'), new OA\Response(response: 403, description: '권한 없음'), new OA\Response(response: 422, description: '입력값 또는 상태 검증 실패')]
    )]
    public function put_api_post_categories_category_8620e8(): void {}

    #[OA\Delete(
        path: '/api/post-categories/{category}',
        operationId: 'delete_api_post_categories_category_5c0a67',
        summary: 'destroyCategory (post-categories/{category})',
        tags: ['Blog'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'category', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 204, description: '성공'), new OA\Response(response: 401, description: '인증 필요'), new OA\Response(response: 403, description: '권한 없음')]
    )]
    public function delete_api_post_categories_category_5c0a67(): void {}

    #[OA\Get(
        path: '/api/posts',
        operationId: 'get_api_posts_473cdd',
        summary: '목록 조회 (posts)',
        tags: ['Blog'],
        responses: [new OA\Response(response: 200, description: '성공')]
    )]
    public function get_api_posts_473cdd(): void {}

    #[OA\Post(
        path: '/api/posts',
        operationId: 'post_api_posts_7295e6',
        summary: '등록 (posts)',
        tags: ['Blog'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: '성공'), new OA\Response(response: 401, description: '인증 필요'), new OA\Response(response: 403, description: '권한 없음'), new OA\Response(response: 422, description: '입력값 또는 상태 검증 실패')]
    )]
    public function post_api_posts_7295e6(): void {}

    #[OA\Put(
        path: '/api/posts/{post}',
        operationId: 'put_api_posts_post_077696',
        summary: '수정 (posts/{post})',
        tags: ['Blog'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'post', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: '성공'), new OA\Response(response: 401, description: '인증 필요'), new OA\Response(response: 403, description: '권한 없음'), new OA\Response(response: 422, description: '입력값 또는 상태 검증 실패')]
    )]
    public function put_api_posts_post_077696(): void {}

    #[OA\Delete(
        path: '/api/posts/{post}',
        operationId: 'delete_api_posts_post_8678aa',
        summary: '삭제 (posts/{post})',
        tags: ['Blog'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'post', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 204, description: '성공'), new OA\Response(response: 401, description: '인증 필요'), new OA\Response(response: 403, description: '권한 없음')]
    )]
    public function delete_api_posts_post_8678aa(): void {}

    #[OA\Get(
        path: '/api/posts/{post}/comments',
        operationId: 'get_api_posts_post_comments_4c4f38',
        summary: '목록 조회 (posts/{post}/comments)',
        tags: ['Blog'],
        parameters: [new OA\Parameter(name: 'post', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: '성공')]
    )]
    public function get_api_posts_post_comments_4c4f38(): void {}

    #[OA\Post(
        path: '/api/posts/{post}/comments',
        operationId: 'post_api_posts_post_comments_166467',
        summary: '등록 (posts/{post}/comments)',
        tags: ['Blog'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'post', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: '성공'), new OA\Response(response: 401, description: '인증 필요'), new OA\Response(response: 403, description: '권한 없음'), new OA\Response(response: 422, description: '입력값 또는 상태 검증 실패')]
    )]
    public function post_api_posts_post_comments_166467(): void {}

    #[OA\Post(
        path: '/api/posts/{post}/likes',
        operationId: 'post_api_posts_post_likes_443de3',
        summary: '등록 (posts/{post}/likes)',
        tags: ['Blog'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'post', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: '성공'), new OA\Response(response: 401, description: '인증 필요'), new OA\Response(response: 403, description: '권한 없음'), new OA\Response(response: 422, description: '입력값 또는 상태 검증 실패')]
    )]
    public function post_api_posts_post_likes_443de3(): void {}

    #[OA\Delete(
        path: '/api/posts/{post}/likes',
        operationId: 'delete_api_posts_post_likes_2a9c05',
        summary: '삭제 (posts/{post}/likes)',
        tags: ['Blog'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'post', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 204, description: '성공'), new OA\Response(response: 401, description: '인증 필요'), new OA\Response(response: 403, description: '권한 없음')]
    )]
    public function delete_api_posts_post_likes_2a9c05(): void {}

    #[OA\Get(
        path: '/api/posts/{slug}',
        operationId: 'get_api_posts_slug_fb1f04',
        summary: '상세 조회 (posts/{slug})',
        tags: ['Blog'],
        parameters: [new OA\Parameter(name: 'slug', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: '성공')]
    )]
    public function get_api_posts_slug_fb1f04(): void {}

    #[OA\Get(
        path: '/api/recommendations/stores',
        operationId: 'get_api_recommendations_stores_0d4e9b',
        summary: 'recommendations (recommendations/stores)',
        tags: ['Stores'],
        responses: [new OA\Response(response: 200, description: '성공')]
    )]
    public function get_api_recommendations_stores_0d4e9b(): void {}

    #[OA\Post(
        path: '/api/register',
        operationId: 'post_api_register_286c00',
        summary: '회원가입 (register)',
        tags: ['Authentication'],
        responses: [new OA\Response(response: 200, description: '성공'), new OA\Response(response: 422, description: '입력값 또는 상태 검증 실패')]
    )]
    public function post_api_register_286c00(): void {}

    #[OA\Post(
        path: '/api/reservations',
        operationId: 'post_api_reservations_5065f6',
        summary: 'storeFromPayload (reservations)',
        tags: ['Reservations & Waitlist'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: '성공'), new OA\Response(response: 401, description: '인증 필요'), new OA\Response(response: 403, description: '권한 없음'), new OA\Response(response: 422, description: '입력값 또는 상태 검증 실패')]
    )]
    public function post_api_reservations_5065f6(): void {}

    #[OA\Patch(
        path: '/api/reservations/{reservation}/status',
        operationId: 'patch_api_reservations_reservation_status_a05f4d',
        summary: '상태 변경 (reservations/{reservation}/status)',
        tags: ['Reservations & Waitlist'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'reservation', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: '성공'), new OA\Response(response: 401, description: '인증 필요'), new OA\Response(response: 403, description: '권한 없음'), new OA\Response(response: 422, description: '입력값 또는 상태 검증 실패')]
    )]
    public function patch_api_reservations_reservation_status_a05f4d(): void {}

    #[OA\Put(
        path: '/api/reviews/{review}',
        operationId: 'put_api_reviews_review_3bda18',
        summary: '수정 (reviews/{review})',
        tags: ['Reviews'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'review', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: '성공'), new OA\Response(response: 401, description: '인증 필요'), new OA\Response(response: 403, description: '권한 없음'), new OA\Response(response: 422, description: '입력값 또는 상태 검증 실패')]
    )]
    public function put_api_reviews_review_3bda18(): void {}

    #[OA\Delete(
        path: '/api/reviews/{review}',
        operationId: 'delete_api_reviews_review_56983b',
        summary: '삭제 (reviews/{review})',
        tags: ['Reviews'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'review', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 204, description: '성공'), new OA\Response(response: 401, description: '인증 필요'), new OA\Response(response: 403, description: '권한 없음')]
    )]
    public function delete_api_reviews_review_56983b(): void {}

    #[OA\Get(
        path: '/api/stores',
        operationId: 'get_api_stores_59c4bf',
        summary: '목록 조회 (stores)',
        tags: ['Stores'],
        responses: [new OA\Response(response: 200, description: '성공')]
    )]
    public function get_api_stores_59c4bf(): void {}

    #[OA\Get(
        path: '/api/stores/{store}',
        operationId: 'get_api_stores_store_2d83cf',
        summary: '상세 조회 (stores/{store})',
        tags: ['Stores'],
        parameters: [new OA\Parameter(name: 'store', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: '성공')]
    )]
    public function get_api_stores_store_2d83cf(): void {}

    #[OA\Get(
        path: '/api/stores/{store}/availability',
        operationId: 'get_api_stores_store_availability_3f25cf',
        summary: '가용성 조회 (stores/{store}/availability)',
        tags: ['Stores'],
        parameters: [new OA\Parameter(name: 'store', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: '성공')]
    )]
    public function get_api_stores_store_availability_3f25cf(): void {}

    #[OA\Get(
        path: '/api/stores/{store}/congestion',
        operationId: 'get_api_stores_store_congestion_d7d218',
        summary: '가용성 조회 (stores/{store}/congestion)',
        tags: ['Stores'],
        parameters: [new OA\Parameter(name: 'store', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: '성공')]
    )]
    public function get_api_stores_store_congestion_d7d218(): void {}

    #[OA\Post(
        path: '/api/stores/{store}/favorite',
        operationId: 'post_api_stores_store_favorite_bbadb0',
        summary: 'favorite (stores/{store}/favorite)',
        tags: ['Stores'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'store', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: '성공'), new OA\Response(response: 401, description: '인증 필요'), new OA\Response(response: 403, description: '권한 없음'), new OA\Response(response: 422, description: '입력값 또는 상태 검증 실패')]
    )]
    public function post_api_stores_store_favorite_bbadb0(): void {}

    #[OA\Delete(
        path: '/api/stores/{store}/favorite',
        operationId: 'delete_api_stores_store_favorite_5fc6a3',
        summary: 'unfavorite (stores/{store}/favorite)',
        tags: ['Stores'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'store', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 204, description: '성공'), new OA\Response(response: 401, description: '인증 필요'), new OA\Response(response: 403, description: '권한 없음')]
    )]
    public function delete_api_stores_store_favorite_5fc6a3(): void {}

    #[OA\Get(
        path: '/api/stores/{store}/menus',
        operationId: 'get_api_stores_store_menus_d4f27a',
        summary: '목록 조회 (stores/{store}/menus)',
        tags: ['Stores'],
        parameters: [new OA\Parameter(name: 'store', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: '성공')]
    )]
    public function get_api_stores_store_menus_d4f27a(): void {}

    #[OA\Get(
        path: '/api/stores/{store}/noshow-policy',
        operationId: 'get_api_stores_store_noshow_policy_c17334',
        summary: '상세 조회 (stores/{store}/noshow-policy)',
        tags: ['Stores'],
        parameters: [new OA\Parameter(name: 'store', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: '성공')]
    )]
    public function get_api_stores_store_noshow_policy_c17334(): void {}

    #[OA\Put(
        path: '/api/stores/{store}/noshow-policy',
        operationId: 'put_api_stores_store_noshow_policy_dd5771',
        summary: '수정 (stores/{store}/noshow-policy)',
        tags: ['Stores'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'store', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: '성공'), new OA\Response(response: 401, description: '인증 필요'), new OA\Response(response: 403, description: '권한 없음'), new OA\Response(response: 422, description: '입력값 또는 상태 검증 실패')]
    )]
    public function put_api_stores_store_noshow_policy_dd5771(): void {}

    #[OA\Get(
        path: '/api/stores/{store}/post-categories',
        operationId: 'get_api_stores_store_post_categories_8fa679',
        summary: 'categories (stores/{store}/post-categories)',
        tags: ['Blog'],
        parameters: [new OA\Parameter(name: 'store', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: '성공')]
    )]
    public function get_api_stores_store_post_categories_8fa679(): void {}

    #[OA\Post(
        path: '/api/stores/{store}/post-categories',
        operationId: 'post_api_stores_store_post_categories_54fff8',
        summary: 'storeCategory (stores/{store}/post-categories)',
        tags: ['Blog'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'store', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: '성공'), new OA\Response(response: 401, description: '인증 필요'), new OA\Response(response: 403, description: '권한 없음'), new OA\Response(response: 422, description: '입력값 또는 상태 검증 실패')]
    )]
    public function post_api_stores_store_post_categories_54fff8(): void {}

    #[OA\Get(
        path: '/api/stores/{store}/reservation-slots',
        operationId: 'get_api_stores_store_reservation_slots_36d943',
        summary: 'slots (stores/{store}/reservation-slots)',
        tags: ['Stores'],
        parameters: [new OA\Parameter(name: 'store', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: '성공')]
    )]
    public function get_api_stores_store_reservation_slots_36d943(): void {}

    #[OA\Post(
        path: '/api/stores/{store}/reservations',
        operationId: 'post_api_stores_store_reservations_f3886b',
        summary: '등록 (stores/{store}/reservations)',
        tags: ['Reservations & Waitlist'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'store', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: '성공'), new OA\Response(response: 401, description: '인증 필요'), new OA\Response(response: 403, description: '권한 없음'), new OA\Response(response: 422, description: '입력값 또는 상태 검증 실패')]
    )]
    public function post_api_stores_store_reservations_f3886b(): void {}

    #[OA\Get(
        path: '/api/stores/{store}/reservations',
        operationId: 'get_api_stores_store_reservations_51a5c4',
        summary: '목록 조회 (stores/{store}/reservations)',
        tags: ['Reservations & Waitlist'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'store', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: '성공'), new OA\Response(response: 401, description: '인증 필요'), new OA\Response(response: 403, description: '권한 없음')]
    )]
    public function get_api_stores_store_reservations_51a5c4(): void {}

    #[OA\Get(
        path: '/api/stores/{store}/reviews',
        operationId: 'get_api_stores_store_reviews_e08861',
        summary: '목록 조회 (stores/{store}/reviews)',
        tags: ['Reviews'],
        parameters: [new OA\Parameter(name: 'store', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: '성공')]
    )]
    public function get_api_stores_store_reviews_e08861(): void {}

    #[OA\Post(
        path: '/api/stores/{store}/reviews',
        operationId: 'post_api_stores_store_reviews_ade0f8',
        summary: '등록 (stores/{store}/reviews)',
        tags: ['Reviews'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'store', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: '성공'), new OA\Response(response: 401, description: '인증 필요'), new OA\Response(response: 403, description: '권한 없음'), new OA\Response(response: 422, description: '입력값 또는 상태 검증 실패')]
    )]
    public function post_api_stores_store_reviews_ade0f8(): void {}

    #[OA\Get(
        path: '/api/stores/{store}/tags',
        operationId: 'get_api_stores_store_tags_bce77e',
        summary: 'tags (stores/{store}/tags)',
        tags: ['Blog'],
        parameters: [new OA\Parameter(name: 'store', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: '성공')]
    )]
    public function get_api_stores_store_tags_bce77e(): void {}

    #[OA\Post(
        path: '/api/stores/{store}/tags',
        operationId: 'post_api_stores_store_tags_3b95e4',
        summary: 'storeTag (stores/{store}/tags)',
        tags: ['Blog'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'store', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: '성공'), new OA\Response(response: 401, description: '인증 필요'), new OA\Response(response: 403, description: '권한 없음'), new OA\Response(response: 422, description: '입력값 또는 상태 검증 실패')]
    )]
    public function post_api_stores_store_tags_3b95e4(): void {}

    #[OA\Post(
        path: '/api/stores/{store}/waitlists',
        operationId: 'post_api_stores_store_waitlists_4df82f',
        summary: '등록 (stores/{store}/waitlists)',
        tags: ['Reservations & Waitlist'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'store', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: '성공'), new OA\Response(response: 401, description: '인증 필요'), new OA\Response(response: 403, description: '권한 없음'), new OA\Response(response: 422, description: '입력값 또는 상태 검증 실패')]
    )]
    public function post_api_stores_store_waitlists_4df82f(): void {}

    #[OA\Put(
        path: '/api/tags/{tag}',
        operationId: 'put_api_tags_tag_cce4df',
        summary: 'updateTag (tags/{tag})',
        tags: ['Blog'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'tag', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: '성공'), new OA\Response(response: 401, description: '인증 필요'), new OA\Response(response: 403, description: '권한 없음'), new OA\Response(response: 422, description: '입력값 또는 상태 검증 실패')]
    )]
    public function put_api_tags_tag_cce4df(): void {}

    #[OA\Delete(
        path: '/api/tags/{tag}',
        operationId: 'delete_api_tags_tag_69ddeb',
        summary: 'destroyTag (tags/{tag})',
        tags: ['Blog'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'tag', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 204, description: '성공'), new OA\Response(response: 401, description: '인증 필요'), new OA\Response(response: 403, description: '권한 없음')]
    )]
    public function delete_api_tags_tag_69ddeb(): void {}

    #[OA\Post(
        path: '/api/uploads/images',
        operationId: 'post_api_uploads_images_d0855d',
        summary: '등록 (uploads/images)',
        tags: ['General'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: '성공'), new OA\Response(response: 401, description: '인증 필요'), new OA\Response(response: 403, description: '권한 없음'), new OA\Response(response: 422, description: '입력값 또는 상태 검증 실패')]
    )]
    public function post_api_uploads_images_d0855d(): void {}

    #[OA\Put(
        path: '/api/users/me',
        operationId: 'put_api_users_me_c3acb7',
        summary: '수정 (users/me)',
        tags: ['Authentication'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: '성공'), new OA\Response(response: 401, description: '인증 필요'), new OA\Response(response: 403, description: '권한 없음'), new OA\Response(response: 422, description: '입력값 또는 상태 검증 실패')]
    )]
    public function put_api_users_me_c3acb7(): void {}

    #[OA\Get(
        path: '/api/users/me',
        operationId: 'get_api_users_me_e929f0',
        summary: '내 정보 조회 (users/me)',
        tags: ['Authentication'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: '성공'), new OA\Response(response: 401, description: '인증 필요'), new OA\Response(response: 403, description: '권한 없음')]
    )]
    public function get_api_users_me_e929f0(): void {}

    #[OA\Get(
        path: '/api/users/me/coupons',
        operationId: 'get_api_users_me_coupons_88d631',
        summary: 'coupons (users/me/coupons)',
        tags: ['My Page'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: '성공'), new OA\Response(response: 401, description: '인증 필요'), new OA\Response(response: 403, description: '권한 없음')]
    )]
    public function get_api_users_me_coupons_88d631(): void {}

    #[OA\Get(
        path: '/api/users/me/favorites',
        operationId: 'get_api_users_me_favorites_a6d27e',
        summary: 'favorites (users/me/favorites)',
        tags: ['My Page'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: '성공'), new OA\Response(response: 401, description: '인증 필요'), new OA\Response(response: 403, description: '권한 없음')]
    )]
    public function get_api_users_me_favorites_a6d27e(): void {}

    #[OA\Get(
        path: '/api/users/me/inquiries',
        operationId: 'get_api_users_me_inquiries_16dd09',
        summary: 'inquiries (users/me/inquiries)',
        tags: ['My Page'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: '성공'), new OA\Response(response: 401, description: '인증 필요'), new OA\Response(response: 403, description: '권한 없음')]
    )]
    public function get_api_users_me_inquiries_16dd09(): void {}

    #[OA\Post(
        path: '/api/users/me/inquiries',
        operationId: 'post_api_users_me_inquiries_f74731',
        summary: 'storeInquiry (users/me/inquiries)',
        tags: ['My Page'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: '성공'), new OA\Response(response: 401, description: '인증 필요'), new OA\Response(response: 403, description: '권한 없음'), new OA\Response(response: 422, description: '입력값 또는 상태 검증 실패')]
    )]
    public function post_api_users_me_inquiries_f74731(): void {}

    #[OA\Get(
        path: '/api/users/me/inquiries/{inquiry}',
        operationId: 'get_api_users_me_inquiries_inquiry_a0f695',
        summary: 'showInquiry (users/me/inquiries/{inquiry})',
        tags: ['My Page'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'inquiry', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: '성공'), new OA\Response(response: 401, description: '인증 필요'), new OA\Response(response: 403, description: '권한 없음')]
    )]
    public function get_api_users_me_inquiries_inquiry_a0f695(): void {}

    #[OA\Get(
        path: '/api/users/me/membership',
        operationId: 'get_api_users_me_membership_efdb0c',
        summary: 'membership (users/me/membership)',
        tags: ['My Page'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: '성공'), new OA\Response(response: 401, description: '인증 필요'), new OA\Response(response: 403, description: '권한 없음')]
    )]
    public function get_api_users_me_membership_efdb0c(): void {}

    #[OA\Get(
        path: '/api/users/me/membership-summary',
        operationId: 'get_api_users_me_membership_summary_dc6cf9',
        summary: 'membershipSummary (users/me/membership-summary)',
        tags: ['My Page'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: '성공'), new OA\Response(response: 401, description: '인증 필요'), new OA\Response(response: 403, description: '권한 없음')]
    )]
    public function get_api_users_me_membership_summary_dc6cf9(): void {}

    #[OA\Get(
        path: '/api/users/me/orders',
        operationId: 'get_api_users_me_orders_5b1862',
        summary: '목록 조회 (users/me/orders)',
        tags: ['Orders & Payments'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: '성공'), new OA\Response(response: 401, description: '인증 필요'), new OA\Response(response: 403, description: '권한 없음')]
    )]
    public function get_api_users_me_orders_5b1862(): void {}

    #[OA\Get(
        path: '/api/users/me/orders/{order}',
        operationId: 'get_api_users_me_orders_order_05b841',
        summary: '상세 조회 (users/me/orders/{order})',
        tags: ['Orders & Payments'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'order', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: '성공'), new OA\Response(response: 401, description: '인증 필요'), new OA\Response(response: 403, description: '권한 없음')]
    )]
    public function get_api_users_me_orders_order_05b841(): void {}

    #[OA\Post(
        path: '/api/users/me/orders/{order}/cancel',
        operationId: 'post_api_users_me_orders_order_cancel_fef5ca',
        summary: 'cancel (users/me/orders/{order}/cancel)',
        tags: ['Orders & Payments'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'order', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: '성공'), new OA\Response(response: 401, description: '인증 필요'), new OA\Response(response: 403, description: '권한 없음'), new OA\Response(response: 422, description: '입력값 또는 상태 검증 실패')]
    )]
    public function post_api_users_me_orders_order_cancel_fef5ca(): void {}

    #[OA\Put(
        path: '/api/users/me/password',
        operationId: 'put_api_users_me_password_645472',
        summary: 'updatePassword (users/me/password)',
        tags: ['My Page'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: '성공'), new OA\Response(response: 401, description: '인증 필요'), new OA\Response(response: 403, description: '권한 없음'), new OA\Response(response: 422, description: '입력값 또는 상태 검증 실패')]
    )]
    public function put_api_users_me_password_645472(): void {}

    #[OA\Get(
        path: '/api/users/me/preferences',
        operationId: 'get_api_users_me_preferences_84bc43',
        summary: 'preferences (users/me/preferences)',
        tags: ['My Page'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: '성공'), new OA\Response(response: 401, description: '인증 필요'), new OA\Response(response: 403, description: '권한 없음')]
    )]
    public function get_api_users_me_preferences_84bc43(): void {}

    #[OA\Put(
        path: '/api/users/me/preferences',
        operationId: 'put_api_users_me_preferences_00896a',
        summary: 'updatePreferences (users/me/preferences)',
        tags: ['My Page'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: '성공'), new OA\Response(response: 401, description: '인증 필요'), new OA\Response(response: 403, description: '권한 없음'), new OA\Response(response: 422, description: '입력값 또는 상태 검증 실패')]
    )]
    public function put_api_users_me_preferences_00896a(): void {}

    #[OA\Get(
        path: '/api/users/me/referral-code',
        operationId: 'get_api_users_me_referral_code_e99712',
        summary: 'referralCode (users/me/referral-code)',
        tags: ['My Page'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: '성공'), new OA\Response(response: 401, description: '인증 필요'), new OA\Response(response: 403, description: '권한 없음')]
    )]
    public function get_api_users_me_referral_code_e99712(): void {}

    #[OA\Post(
        path: '/api/users/me/referrals/claim',
        operationId: 'post_api_users_me_referrals_claim_b69e24',
        summary: 'claimReferral (users/me/referrals/claim)',
        tags: ['My Page'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: '성공'), new OA\Response(response: 401, description: '인증 필요'), new OA\Response(response: 403, description: '권한 없음'), new OA\Response(response: 422, description: '입력값 또는 상태 검증 실패')]
    )]
    public function post_api_users_me_referrals_claim_b69e24(): void {}

    #[OA\Get(
        path: '/api/users/me/reservations',
        operationId: 'get_api_users_me_reservations_9b4ced',
        summary: '내 정보 조회 (users/me/reservations)',
        tags: ['Reservations & Waitlist'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: '성공'), new OA\Response(response: 401, description: '인증 필요'), new OA\Response(response: 403, description: '권한 없음')]
    )]
    public function get_api_users_me_reservations_9b4ced(): void {}

    #[OA\Get(
        path: '/api/users/me/reservations/{reservation}',
        operationId: 'get_api_users_me_reservations_reservation_78f67e',
        summary: 'showMine (users/me/reservations/{reservation})',
        tags: ['Reservations & Waitlist'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'reservation', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: '성공'), new OA\Response(response: 401, description: '인증 필요'), new OA\Response(response: 403, description: '권한 없음')]
    )]
    public function get_api_users_me_reservations_reservation_78f67e(): void {}

    #[OA\Delete(
        path: '/api/users/me/reservations/{reservation}',
        operationId: 'delete_api_users_me_reservations_reservation_a1c2c7',
        summary: 'cancelMine (users/me/reservations/{reservation})',
        tags: ['Reservations & Waitlist'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'reservation', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 204, description: '성공'), new OA\Response(response: 401, description: '인증 필요'), new OA\Response(response: 403, description: '권한 없음')]
    )]
    public function delete_api_users_me_reservations_reservation_a1c2c7(): void {}

    #[OA\Get(
        path: '/api/users/me/waitlists',
        operationId: 'get_api_users_me_waitlists_11ce2c',
        summary: '내 정보 조회 (users/me/waitlists)',
        tags: ['Reservations & Waitlist'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: '성공'), new OA\Response(response: 401, description: '인증 필요'), new OA\Response(response: 403, description: '권한 없음')]
    )]
    public function get_api_users_me_waitlists_11ce2c(): void {}

    #[OA\Delete(
        path: '/api/users/me/waitlists/{waitlist}',
        operationId: 'delete_api_users_me_waitlists_waitlist_7bedc6',
        summary: 'cancelMine (users/me/waitlists/{waitlist})',
        tags: ['Reservations & Waitlist'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'waitlist', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 204, description: '성공'), new OA\Response(response: 401, description: '인증 필요'), new OA\Response(response: 403, description: '권한 없음')]
    )]
    public function delete_api_users_me_waitlists_waitlist_7bedc6(): void {}

    #[OA\Post(
        path: '/api/webhooks/toss-payments',
        operationId: 'post_api_webhooks_toss_payments_e754f8',
        summary: '결제 웹훅 (webhooks/toss-payments)',
        tags: ['Orders & Payments'],
        responses: [new OA\Response(response: 200, description: '성공'), new OA\Response(response: 422, description: '입력값 또는 상태 검증 실패')]
    )]
    public function post_api_webhooks_toss_payments_e754f8(): void {}
}
