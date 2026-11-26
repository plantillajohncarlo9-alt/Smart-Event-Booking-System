package com.smartevent;

import java.time.LocalDate;
import java.util.List;

import org.springframework.data.jpa.repository.JpaRepository;

public interface BookingRepository extends JpaRepository<Booking, Long> {
    boolean existsByVenueAreaAndEventDateAndTimeSlot(String venueArea, LocalDate eventDate, String timeSlot);
    List<Booking> findAllByOrderByIdDesc();
}