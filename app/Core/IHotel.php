<?php

namespace App\Core;
use App\Models\HotelToken;

interface IHotel
{
    /**
     * The constructor receives the provider's configuration (API keys, etc.).
     */
    public function __construct(HotelToken $token);

    /**
     * Get location and hotel name suggestions for a search query.
     * Maps to the 3TN 'autocomplete' method.
     *
     * @param string $searchText The text the user is typing.
     * @return array A standardized list of suggestions.
     */
    public function getSearchSuggestions(string $searchText): array;

    /**
     * Perform a full search for available hotels.
     * This is the first step in the booking flow and will create the booking context.
     * Maps to the 3TN 'availability' method.
     *
     * @param array $searchData Standardized search data (e.g., city, dates, guest info).
     * @return array A standardized response including the hotel list and a `contextId`.
     */
    public function searchHotels(array $searchData): array;

    /**
     * Get detailed information for a single hotel.
     * Maps to the 3TN 'hotelDetails' method.
     *
     * @param string $hotelId The unique identifier for the hotel.
     * @param array $options Provider-specific options (like 'source' for 3TN).
     * @return array Standardized hotel details.
     */
    public function getHotelDetails(array $offer, array $options = []): array;

    /**
     * Re-validates the price for selected rooms before booking.
     * This is the second step and uses the context from the search.
     * Maps to the 3TN 'checkRate' method.
     *
     * @param string $contextId The ID for the cached booking context from the search.
     * @param array $rateKeys Standardized list of selected room/rate identifiers.
     * @return array Standardized response with verified rates and an updated context.
     */
    public function checkRates(string $contextId, array $rateKeys): array;

    /**
     * Creates the final booking.
     * This is the third step and uses the context that now includes the booking token.
     * Maps to the 3TN 'book' method.
     *
     * @param string $contextId The ID for the cached booking context.
     * @param array $guestDetails Standardized guest and customer information.
     * @param array $paymentDetails Standardized payment information.
     * @return array Standardized booking confirmation.
     */
    public function createBooking(string $contextId,  string $bookingUuid, array $guestDetails, array $paymentDetails): \App\Models\Order;

    /**
     * Retrieves a list of bookings made by the customer.
     * Maps to the 3TN 'getBookings' method.
     *
     * @param array $filterData Standardized filter data (e.g., fromDate, toDate).
     * @return array A standardized list of booking summaries.
     */
    public function getBookingList(array $filterData): array;

    /**
     * Cancels an existing booking.
     * Maps to the 3TN 'cancel' method.
     *
     * @param string $bookingId The provider's unique ID for the booking.
     * @param array $options Provider-specific options (like 'bookingSource' for 3TN).
     * @return array Standardized cancellation confirmation.
     */
    public function cancelBooking(string $bookingId, array $options = []): array;


    /**
     * Get the account balance
     */
    public function getBalanace(): array;

}